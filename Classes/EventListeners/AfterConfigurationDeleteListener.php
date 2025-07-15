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
use Code711\SiteConfigurationEvents\Events\AfterSiteConfigurationDeleteEvent;
use TYPO3\CMS\Core\Core\Environment;

class AfterConfigurationDeleteListener
{
    use ExtensionIsActiveTrait;
    public function __invoke(AfterSiteConfigurationDeleteEvent $event): void
    {
        if ($this->isActive()) {
            try {
                $siteIdentifier = $event->getSiteIdentifier();
                $configFileName = 'config.yaml';
                $path = Environment::getConfigPath() . '/sites';
                $folder   = $path . '/' . $siteIdentifier;
                $fileName = $folder . '/' . $configFileName;

                $git     = GitApiServiceFactory::get();
                $branch  = $git->getBranchName($siteIdentifier);
                $message = $git->getCommitMessage($siteIdentifier, 'delete site');
                if ($git->createBranch($branch)) {
                    $filebase = \str_replace(Environment::getProjectPath(), '', $fileName);
                    if ($git->deleteFile($filebase, $branch, $message)) {
                        $git->createMergeRequest($siteIdentifier, $branch, 'delete site');
                    }
                }
            } catch (\InvalidArgumentException $e) {
            }
        }
    }
}
