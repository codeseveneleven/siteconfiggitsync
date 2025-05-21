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

namespace Code711\SiteConfigGitSync\Factory;

use Code711\SiteConfigGitSync\Domain\Service\GithubApiService;
use Code711\SiteConfigGitSync\Domain\Service\GitlabApiService;
use Code711\SiteConfigGitSync\Interfaces\GitApiServiceInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitApiServiceFactory
{
    public const SUPPORTEDSERVICES = [
        'gitlab',
        'github',
    ];

    public static function get(): GitApiServiceInterface
    {
        $config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('siteconfiggitsync');

        $service = 'gitlab';
        if (\is_array($config) && isset($config['gitservice']) && \is_string($config['gitservice']) && \in_array($config['gitservice'], self::SUPPORTEDSERVICES)) {
            $service = $config['gitservice'];
        }

        return match ($service) {
            'gitlab' => GeneralUtility::makeInstance(GitlabApiService::class),
            'github' => GeneralUtility::makeInstance(GithubApiService::class),
        };
    }
}
