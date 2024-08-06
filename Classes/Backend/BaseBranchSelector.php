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

use Code711\SiteConfigGitSync\Factory\GitApiServiceFactory;
use Gitlab\Exception\RuntimeException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BaseBranchSelector
{
    /**
     * @param array<string,string|int> $parameter
     *
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function render(array $parameter = []): string
    {

        $config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('siteconfiggitsync');

        $out = '<p>Please enter Gitlab site and API token</p>';

        if (!empty($config['gitlabserver']) && !empty($config['http_auth_token'])) {
            try {
                $branches = GitApiServiceFactory::get()->getBranches();

                $out = sprintf('<select name="%1$s" id="em-%1$s">', $parameter['fieldName']);
                foreach ($branches as $branch) {
                    $out .= sprintf('<option value="%1$s" %3$s>%1$s (Last commit: %2$s)</option>', $branch['name'], $branch['commit']['created_at'], $branch['name'] === $parameter['fieldValue'] ? 'selected' : '');
                }
                $out .=  '</select>';
            } catch (RuntimeException $e) {
                $out = '<p>Git server says: ' . $e->getMessage() . '</p>';
            } catch (\InvalidArgumentException $e) {
                $out = '<p>' . $e->getMessage() . '</p>';
            }
        }
        return $out;
    }
}
