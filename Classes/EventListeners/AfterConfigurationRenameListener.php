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

namespace Code711\SiteConfigGitSync\EventListeners;

use Code711\SiteConfigGitSync\Factory\GitApiServiceFactory;
use Code711\SiteConfigGitSync\Traits\ExtensionIsActiveTrait;
use Code711\SiteConfigurationEvents\Events\AfterSiteConfigurationRenameEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Core\Environment;

#[AsEventListener(
    identifier: 'siteconfiggitsync/pushSiteConfigToGitAfterRename'
)]
class AfterConfigurationRenameListener
{
    use ExtensionIsActiveTrait;
    public const CONFIG = 'config.yaml';
    public const SETTINGS = 'settings.yaml';
    public function __invoke(AfterSiteConfigurationRenameEvent $event): void
    {
        if ($this->isActive()) {
            try {
                $currentIdentifier = $event->getCurrentIdentifier();
                $newIdentifier = $event->getNewIdentifier();
                $path = Environment::getConfigPath() . '/sites';

                $folderOld   = $path . '/' . $currentIdentifier;
                $fileNameOld = $folderOld . '/' . self::CONFIG;
                $settingsFileNameOld = $folderOld . '/' . self::SETTINGS;

                $folderNew   = $path . '/' . $newIdentifier;
                $fileNameNew = $folderNew . '/' . self::CONFIG;
                $settingsFileNameNew = $folderNew . '/' . self::SETTINGS;

                $git     = GitApiServiceFactory::get();
                $branch  = $git->getBranchName($newIdentifier);
                $message = $git->getCommitMessage($newIdentifier, 'rename site identifier');

                if ($git->createBranch($branch)) {

                    if (is_file($settingsFileNameOld) || is_file($settingsFileNameNew)) {
                        $settingsFilebaseOld = \str_replace(Environment::getProjectPath(), '', $settingsFileNameOld);
                        $settingsFilebaseNew = \str_replace(Environment::getProjectPath(), '', $settingsFileNameNew);
                        $git->moveFile($settingsFilebaseOld, $settingsFilebaseNew, $message, $branch);
                    }

                    $filebaseOld = \str_replace(Environment::getProjectPath(), '', $fileNameOld);
                    $filebaseNew = \str_replace(Environment::getProjectPath(), '', $fileNameNew);

                    if ($git->moveFile($filebaseOld, $filebaseNew, $message, $branch)) {
                        $git->createMergeRequest($newIdentifier, $branch, 'rename site identifier');
                    }
                }
            } catch (\InvalidArgumentException $e) {
            }
        }
    }
}
