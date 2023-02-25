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

class SiteConfiguration extends \TYPO3\CMS\Core\Configuration\SiteConfiguration
{
    public function write(string $siteIdentifier, array $configuration, bool $protectPlaceholders = false): void
    {
        parent::write($siteIdentifier, $configuration, $protectPlaceholders);
    }

    public function rename(string $currentIdentifier, string $newIdentifier): void
    {
        parent::rename($currentIdentifier, $newIdentifier);
    }

    public function delete(string $siteIdentifier): void
    {
        parent::delete($siteIdentifier);
    }
}
