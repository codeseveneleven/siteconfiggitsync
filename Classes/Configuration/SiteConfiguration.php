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

namespace Code711\SiteConfigGitSync\Configuration;

use Code711\SiteConfigGitSync\Factory\GitApiServiceFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteConfiguration extends \TYPO3\CMS\Core\Configuration\SiteConfiguration
{
    /**
     * @param string $siteIdentifier
     * @param array<string,mixed> $configuration
     * @param bool $protectPlaceholders
     *
     * @throws \TYPO3\CMS\Core\Configuration\Exception\SiteConfigurationWriteException
     */
    public function write(string $siteIdentifier, array $configuration, bool $protectPlaceholders = false): void
    {
        parent::write($siteIdentifier, $configuration, $protectPlaceholders);
        try {
            $folder   = $this->configPath . '/' . $siteIdentifier;
            $fileName = $folder . '/' . $this->configFileName;

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
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($message);
        }
    }

    public function rename(string $currentIdentifier, string $newIdentifier): void
    {
        parent::rename($currentIdentifier, $newIdentifier);
        try {
            $folderOld = $this->configPath . '/' . $currentIdentifier;
            $fileNameOld = $folderOld . '/' . $this->configFileName;
            $folderNew = $this->configPath . '/' . $newIdentifier;
            $fileNameNew = $folderNew . '/' . $this->configFileName;

            $git = GitApiServiceFactory::get();
            $branch = $git->getBranchName($newIdentifier);
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

    public function delete(string $siteIdentifier): void
    {
        parent::delete($siteIdentifier);
        try {
            $folder   = $this->configPath . '/' . $siteIdentifier;
            $fileName = $folder . '/' . $this->configFileName;

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
