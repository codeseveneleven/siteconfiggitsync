<?php

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

namespace Code711\SiteConfigGitSync\Factory;

use Code711\SiteConfigGitSync\Domain\Service\GitlabApiService;
use Code711\SiteConfigGitSync\Interfaces\GitApiServiceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitApiServiceFactory
{
    public static function get(): GitApiServiceInterface
    {
        return GeneralUtility::makeInstance(GitlabApiService::class);
    }
}
