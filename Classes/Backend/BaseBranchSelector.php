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

namespace Code711\SiteConfigGitSync\Backend;

use Gitlab\Exception\RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BaseBranchSelector
{
    public function render(array $parameter = []): string
    {
        $config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('siteconfiggitsync');

        $out = '<p>Please enter Gitlab site and API token</p>';

        if (!empty($config['gitlabserver']) && !empty($config['http_auth_token'])) {
            try {
                $projectpath = trim(\parse_url($config['gitlabserver'], PHP_URL_PATH), '/');

                $client = new \Gitlab\Client();
                $client->setUrl($config['gitlabserver']);
                $client->authenticate($config['http_auth_token'], \Gitlab\Client::AUTH_HTTP_TOKEN);
                //$result = $client->projects()->all(['membership'=>true]);

                $result = $client->repositories()->branches($projectpath);

                $out = sprintf('<select name="%1$s" id="em-%1$s">', $parameter['fieldName']);
                foreach ($result as $branch) {
                    $out .= sprintf('<option value="%1$s" %3$s>%1$s (Last commit: %2$s)</option>', $branch['name'], $branch['commit']['created_at'], $branch['name'] === $parameter['fieldValue'] ? 'selected' : '');
                }
                $out .=  '</select>';
            } catch (RuntimeException $e) {
                $out = '<p>Gitlab says: ' . $e->getMessage() . '</p>';
            }
        }
        return $out;
    }
}
