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
use Code711\SiteConfigurationEvents\Events\AfterSiteConfigurationRenameEvent;
use TYPO3\CMS\Core\Core\Environment;

class AfterConfigurationRenameListener
{
    public function __invoke(AfterSiteConfigurationRenameEvent $event): void
    {
        if (Environment::getContext()->isProduction() || Environment::getContext()->isDevelopment()) {
            try {
                $currentIdentifier = $event->getCurrentIdentifier();
                $newIdentifier = $event->getNewIdentifier();
                $configFileName = 'config.yaml';
                $path = Environment::getConfigPath() . '/sites';

                $folderOld   = $path . '/' . $currentIdentifier;
                $fileNameOld = $folderOld . '/' . $configFileName;
                $folderNew   = $path . '/' . $newIdentifier;
                $fileNameNew = $folderNew . '/' . $configFileName;

                $git     = GitApiServiceFactory::get();
                $branch  = $git->getBranchName($newIdentifier);
                $message = $git->getCommitMessage($newIdentifier, 'rename site identifier');

                if ($git->createBranch($branch)) {
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
