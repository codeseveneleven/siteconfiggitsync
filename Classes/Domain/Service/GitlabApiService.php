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
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitlabApiService implements GitApiServiceInterface
{
    /**
     * @var array<string,string>
     */
    protected array $config;

    /**
     * @var array<int,array<string,string|int>>
     */
    protected array $branchescache = [];

    public function __construct()
    {
        $this->config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('siteconfiggitsync');

        if (empty($this->config['gitlabserver']) || empty($this->config['http_auth_token'])) {
            throw new \InvalidArgumentException('EXT:siteconfiggitsync is not configured yet!!!', 1677369373);
        }
    }

    public function getHost(): string
    {
        $uri = \parse_url($this->config['gitlabserver']);
        if (isset($uri['host']) && isset($uri['scheme'])) {
            return $uri['scheme'] . '://' . $uri['host'] . '/';
        }
        return $this->config['gitlabserver'];
    }
    public function getProject(): string
    {
        return trim((string)\parse_url($this->config['gitlabserver'], PHP_URL_PATH), '/');
    }

    public function getBranches(): array
    {
        if (empty($this->branchescache)) {
            $client = $this->connect();

            //$result = $client->projects()->all(['membership'=>true]);
            $this->branchescache = $client->repositories()->branches($this->getProject());
        }
        return $this->branchescache;
    }

    public function connect(): Client
    {
        $client = new \Gitlab\Client();
        $client->setUrl($this->getHost());
        $client->authenticate($this->config['http_auth_token'], \Gitlab\Client::AUTH_HTTP_TOKEN);
        return $client;
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
        return sprintf($this->config['branch_naming_template'], $identifier);
    }

    public function getAuthor(): array
    {
        $author = [
            'username'=>'unknown',
            'email'=>'n/a',
        ];
        if ($GLOBALS['BE_USER'] instanceof BackendUserAuthentication) {
            $uid = $GLOBALS['BE_USER']->getOriginalUserIdWhenInSwitchUserMode();
            if ($uid === null) {
                $context = GeneralUtility::makeInstance(Context::class);
                $uid = $context->getPropertyFromAspect('backend.user', 'id');
            }
            /** @var array{uid: int, username: string, email: ?string} $user */
            $user = BackendUtility::getRecord('be_users', $uid);
            $author['username']=$user['username'];
            if ($user['email']) {
                $author['email'] = $user['email'];
            }
        }
        return $author;
    }

    public function getCommitMessage(string $identifier, string $addtional_info = ''): string
    {
        $author = $this->getAuthor();
        $username = sprintf('%s (%s)', $author['username'], $author['email']);
        return \sprintf($this->config['commit_message'], $identifier, $username, $addtional_info);
    }
    public function getMergeRequestMessage(string $identifier, string $addtional_info = ''): string
    {
        $author = $this->getAuthor();
        $username = sprintf('%s (%s)', $author['username'], $author['email']);
        return \sprintf($this->config['mergerequest_message'], $identifier, $username, $addtional_info);
    }

    public function createBranch(string $newbranch): bool
    {
        if ($this->hasBranch($newbranch)) {
            return true;
        }
        $frombranch = $this->config['main_branch'];
        $client = $this->connect();
        try {
            $client->repositories()->createBranch($this->getProject(), $newbranch, $frombranch);
        } catch (\Gitlab\Exception\ValidationFailedException $e) {
            // ok, it exists ?
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
        $client = $this->connect();
        try {
            $raw = $client->repositoryFiles()->getRawFile($this->getProject(), $oldfilename, $branch);
        } catch (RuntimeException $e) {
            return $this->commitFile($newfilename, (string)\file_get_contents(Environment::getProjectPath() . '/' . $newfilename), $commitmessage, $branch);
        }
        $author = $this->getAuthor();

        try {
            //$client->repositories()->createCommit( $this->getProject(),  [
            //            'branch'=>$branch,
            //            'commit_message'=>$commitmessage,
            //            'actions'=>[
            //                'action'=>['move'],
            //                'file_path'=> $newfilename,
            //                'previous_path'=>$oldfilename,
            //                'content'=> \file_get_contents( Environment::getProjectPath().'/'.$newfilename)
            //            ],
            //            'author_email'=>$author['email'],
            //            'author_name'=>$author['username'],
            //        ] );
            $this->commitFile($newfilename, (string)\file_get_contents(Environment::getProjectPath() . '/' . $newfilename), $commitmessage, $branch);
            $client->repositoryFiles()->deleteFile($this->getProject(), [
                'file_path'=>$oldfilename,
                'branch'=>$branch,
                'commit_message'=>$commitmessage,
                'author_email'=>$author['email'],
                'author_name'=>$author['username'],
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function deleteFile(string $filename, string $branch, string $commitmessage): bool
    {
        $author = $this->getAuthor();
        $filename = trim($filename, '/');
        $client = $this->connect();
        try {
            $client->repositoryFiles()->deleteFile($this->getProject(), [
                'file_path'      => $filename,
                'branch'         => $branch,
                'commit_message' => $commitmessage,
                'author_email'   => $author['email'],
                'author_name'    => $author['username'],
            ]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function commitFile(string $filename, string $filecontent, string $commitmessage, string $branch): bool
    {
        $filename = trim($filename, '/');
        $client = $this->connect();

        try {
            $raw = $client->repositoryFiles()->getRawFile($this->getProject(), $filename, $branch);
        } catch (RuntimeException $e) {
            $raw = null;
        }

        $author = $this->getAuthor();

        $payload = [
            'file_path'=> $filename,
            'branch'=>$branch,
            'content'=>$filecontent,
            'commit_message'=>$commitmessage,
            'author_email'=>$author['email'],
            'author_name'=>$author['username'],
        ];

        $result = null;
        if ($raw) {
            // update
            if ($raw !== $filecontent) {
                $result = $client->repositoryFiles()->updateFile($this->getProject(), $payload);
            }
        } else {
            //create
            $result = $client->repositoryFiles()->createFile($this->getProject(), $payload);
        }

        return is_array($result) && $result['file_path'] === $filename;
    }

    public function createMergeRequest(string $identifier, string $branch, string $additional_info=''): void
    {
        try {
            $client = $this->connect();
            $payload = [];
            if ((int)$this->config['mergerequest_assign'] > 0) {
                $payload['assignee_id'] = (int)$this->config['mergerequest_assign'];
            }
            $result = $client->mergeRequests()->create(
                $this->getProject(),
                $branch,
                $this->config['main_branch'],
                $this->getMergeRequestMessage($identifier, $additional_info),
                $payload
            );
        } catch (RuntimeException $e) {
            // allready exists
        }
    }

    public function getMembers(): array
    {
        $client = $this->connect();
        $members = [];
        $membersRaw = $client->projects()->members($this->getProject());
        foreach ($membersRaw as $member) {
            if ($member['access_level'] >= 30 && $member['state']==='active') {
                $members[] = [
                    'id'       => $member['id'],
                    'username' => $member['username'],
                    'name'     => $member['name'],
                ];
            }
        }
        $membersRaw = $client->groups()->members(dirname($this->getProject()));
        $groupMembers = [];
        foreach ($membersRaw as $member) {
            if ($member['access_level'] >= 30 && $member['state']==='active') {
                $groupMembers[] = [
                    'id'       => $member['id'],
                    'username' => $member['username'],
                    'name'     => $member['name'],
                ];
            }
        }
        return \array_merge_recursive($members, $groupMembers);
    }
}
