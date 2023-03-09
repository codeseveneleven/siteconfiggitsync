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
use Code711\SiteConfigurationEvents\Events\AfterSiteConfigurationWriteEvent;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AfterConfigurationWriteListener
{
    public function __invoke(AfterSiteConfigurationWriteEvent $event): void
    {
        if (Environment::getContext()->isProduction()) {
            try {
                $siteIdentifier = $event->getSiteIdentifier();
                $configFileName = 'config.yaml';
                $path = Environment::getConfigPath() . '/sites';
                $folder   = $path . '/' . $siteIdentifier;
                $fileName = $folder . '/' . $configFileName;

                $git     = GitApiServiceFactory::get();
                $branch  = $git->getBranchName($siteIdentifier);
                $message = $git->getCommitMessage($siteIdentifier, 'create or update');
                if ($git->createBranch($branch)) {
                    $filebase = \str_replace(Environment::getProjectPath(), '', $fileName);
                    if ($git->commitFile($filebase, (string)\file_get_contents($fileName), $message, $branch)) {
                        $git->createMergeRequest($siteIdentifier, $branch, 'create or update');
                    }
                }
            } catch (\InvalidArgumentException $e) {
                $message = GeneralUtility::makeInstance(
                    FlashMessage::class,
                    $e->getMessage(),
                    '',
                    FlashMessage::WARNING,
                    true
                );

                $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
                $messageQueue        = $flashMessageService->getMessageQueueByIdentifier();
                $messageQueue->addMessage($message);
            }
        }
    }
}
