<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project.
 *
 * @author Frank Berger <fberger@code711.de>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Code711\SiteConfigGitSync\Domain\Service;

use Code711\SiteConfigGitSync\Interfaces\GitApiServiceInterface;

use Github\Api\GitData\References;
use Github\Api\Issue;
use Github\Api\PullRequest;
use Github\Api\Repository\Contents;
use Github\AuthMethod;
use Github\Client;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GithubApiService implements GitApiServiceInterface
{
    /**
     * @var array<string,string>
     */
    protected array $config;

    /**
     * @var array<int,array<string,string|int>>
     */
    protected array $branchescache = [];

    protected ?array $repository = null;
    public function __construct()
    {
        $this->config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('siteconfiggitsync');
        if (empty($this->config['gitlabserver']) || empty($this->config['http_auth_token'])) {
            throw new \InvalidArgumentException('EXT:siteconfiggitsync is not configured yet!!!', 1677369373);
        }
    }

    public function hasBranch(string $branch): bool
    {
        $branches = $this->getBranches();
        foreach ($branches as $gitbranch) {
            if ($gitbranch['name'] === $branch) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getBranches(): array
    {
        if (empty($this->branchescache)) {
            $client = $this->connect();
            $branches = $client->api('repo')->branches($this->getHost(), $this->getProject());

            foreach ($branches as $branch) {
                $branch['commit']['created_at'] = $branch['commit']['sha'];
                $this->branchescache[] = $branch;
            }

        }
        return $this->branchescache;
    }

    public function connect(): Client
    {
        $client = new Client();
        $client->authenticate($this->config['http_auth_token'], AuthMethod::ACCESS_TOKEN);
        return $client;
    }

    /**
     * The API needs the user or company in this case, the host itself is known
     * @return string
     */
    public function getHost(): string
    {
        $path = trim((string)\parse_url($this->config['gitlabserver'], PHP_URL_PATH), '/');
        $aPath = GeneralUtility::trimExplode('/', $path, true);
        array_pop($aPath);
        $path = implode('/', $aPath);
        return $path;
    }

    /**
     * In GITHUB World the project is the last part of the url
     * @return string
     */
    public function getProject(): string
    {
        $path = trim((string)\parse_url($this->config['gitlabserver'], PHP_URL_PATH), '/');
        $aPath = GeneralUtility::trimExplode('/', $path, true);
        return array_pop($aPath);
    }

    /**
     * @inheritDoc
     */
    public function getBranch(string $branch): ?array
    {
        $branches = $this->getBranches();
        foreach ($branches as $gitbranch) {
            if ($gitbranch['name'] === $branch) {
                return $gitbranch;
            }
        }
        return null;
    }

    public function getBranchName(string $identifier): string
    {
        return \sprintf($this->config['branch_naming_template'], $identifier);
    }

    public function getCommitMessage(string $identifier, string $addtional_info = ''): string
    {
        $author = $this->getAuthor();
        $username = \sprintf('%s (%s)', $author['username'], $author['email']);
        return \sprintf($this->config['commit_message'], $identifier, $username, $addtional_info);
    }

    /**
     * @inheritDoc
     */
    public function getAuthor(): array
    {
        $author = [
            'username' => 'unknown',
            'email' => 'n/a',
        ];
        if ($GLOBALS['BE_USER'] instanceof BackendUserAuthentication) {
            $uid = $GLOBALS['BE_USER']->getOriginalUserIdWhenInSwitchUserMode();
            if ($uid === null) {
                $context = GeneralUtility::makeInstance(Context::class);
                $uid = $context->getPropertyFromAspect('backend.user', 'id');
            }
            /** @var array{uid: int, username: string, email: ?string} $user */
            $user = BackendUtility::getRecord('be_users', $uid);
            if ($user && isset($user['username'])) {
                $author['username'] = $user['username'];
                if ($user['email']) {
                    $author['email'] = $user['email'];
                }
            }
        }
        return $author;
    }

    public function getMergeRequestMessage(string $identifier, string $addtional_info = ''): string
    {
        $author = $this->getAuthor();
        $username = \sprintf('%s (%s)', $author['username'], $author['email']);
        return \sprintf($this->config['mergerequest_message'], $identifier, $username, $addtional_info);
    }

    public function createBranch(string $newbranch): bool
    {
        if ($this->hasBranch($newbranch)) {
            return true;
        }
        $frombranch = $this->getBranch($this->config['main_branch']);
        $client = $this->connect();
        try {
            /** @var References $references */
            $references = $client->api('git')->references();
            $result = $references->create($this->getHost(), $this->getProject(), [
                'ref' => 'refs/heads/' . $newbranch,
                'sha' => $frombranch['commit']['sha'],
            ]);

        } catch (\Exception $e) {
            // other error ?
            return false;
        }
        $this->branchescache = [];
        $this->getBranches();
        return true;
    }

    public function moveFile(string $oldfilename, string $newfilename, string $commitmessage, string $branch): bool
    {
        $oldfilename = trim($oldfilename, '/');
        $newfilename = trim($newfilename, '/');

        [ $oldraw, $oldmeta ] = $this->getFile($oldfilename, $branch);
        //[ $newraw, $newmeta ] = $this->getFile($newfilename, $branch);

        if ($oldraw === null) {
            return $this->commitFile($newfilename, (string)\file_get_contents(Environment::getProjectPath() . '/' . $newfilename), $commitmessage, $branch);
        }

        try {
            if (!$this->commitFile($newfilename, (string)\file_get_contents(Environment::getProjectPath() . '/' . $newfilename), $commitmessage, $branch)) {
                return false;
            }
            return $this->deleteFile($oldfilename, $branch, $commitmessage);

        } catch (\Exception $e) {
            $x = 1;
        }
        return false;
    }

    public function deleteFile(string $filename, string $branch, string $commitmessage): bool
    {
        try {
            $filename = trim($filename, '/');
            $client  = $this->connect();
            /** @var Contents $content */
            $content = $client->api('repo')->contents();
            if ($content->exists($this->getHost(), $this->getProject(), $filename, 'refs/heads/' . $branch)) {

                $meta = $content->show($this->getHost(), $this->getProject(), $filename, 'refs/heads/' . $branch);

                $client  = $this->connect();
                /** @var Contents $content */
                $content = $client->api('repo')->contents();

                $result = $content->rm($this->getHost(), $this->getProject(), $filename, $commitmessage, $meta['sha'], 'refs/heads/' . $branch);
                return true;
            }
        } catch (\Exception $e) {
            $x = 1;
        }
        return false;
    }

    public function commitFile(string $filename, string $filecontent, string $commitmessage, string $branch): bool
    {
        $filename = trim($filename, '/');

        [ $raw, $oldfile ] = $this->getFile($filename, $branch);

        $client = $this->connect();
        /** @var Contents $content */
        $content = $client->api('repo')->contents();

        $result = null;
        if ($raw) {
            // update
            if ($raw !== $filecontent) {
                $result = $content->update($this->getHost(), $this->getProject(), $filename, $filecontent, $commitmessage, $oldfile['sha'], 'refs/heads/' . $branch);

            }
        } else {
            //create
            $result = $content->create($this->getHost(), $this->getProject(), $filename, $filecontent, $commitmessage, 'refs/heads/' . $branch);
        }

        return is_array($result) && $result['content']['path'] === $filename;

    }

    public function createMergeRequest(string $identifier, string $branch, string $additional_info = ''): void
    {
        try {
            $client = $this->connect();
            /** @var PullRequest $pr */
            $pr = $client->api('pr');

            $payload = [
                'title' => $this->getMergeRequestMessage($identifier, $additional_info),
                'body' => 'updates from site',
                'head' => 'refs/heads/' . $branch,
                'base' => $this->config['main_branch'],
            ];

            //if ((int)$this->config['mergerequest_assign'] > 0) {
            //    $payload['assignee_id'] = (int)$this->config['mergerequest_assign'];
            //}
            $result = $pr->create($this->getHost(), $this->getProject(), $payload);

            if ((int)$this->config['mergerequest_assign'] > 0) {

                /** @var Issue $issues */
                $issues   = $client->api('issues');
                $possible = $issues->assignees()->listAvailable($this->getHost(), $this->getProject());
                foreach ($possible as $user) {
                    if ($user['id'] === (int)$this->config['mergerequest_assign']) {
                        $issues->assignees()->add($this->getHost(), $this->getProject(), $result['number'], [
                            'assignees' => [
                                $user['login'],
                            ],
                        ]);
                    }
                }

            }

            $x = 1;
        } catch (\Exception $e) {
            // allready exists
            $x = 1;
        }
    }

    /**
     * @inheritDoc
     */
    public function getMembers(): array
    {
        $members = [];
        $client = $this->connect();

        /** @var Issue $issues */
        $issues   = $client->api('issues');
        $possible = $issues->assignees()->listAvailable($this->getHost(), $this->getProject());
        foreach ($possible as $user) {
            $user['name'] = $user['login'];
            $user['username'] = $user['login'];
            $members[] = $user;
        }
        return $members;
    }

    /**
     * @param Contents $content
     * @param string $filename
     * @param string $branch
     *
     * @return array
     */
    private function getFile(string $filename, string $branch): array
    {
        $raw = null;
        $meta = [];
        $client = $this->connect();
        /** @var Contents $content */
        $content = $client->api('repo')->contents();
        if ($content->exists($this->getHost(), $this->getProject(), $filename, 'refs/heads/' . $branch)) {
            $meta = $content->show($this->getHost(), $this->getProject(), $filename, 'refs/heads/' . $branch);
            $raw     = $content->rawDownload($this->getHost(), $this->getProject(), $filename, 'refs/heads/' . $branch);
        }
        return [ $raw, $meta ];
    }
}
