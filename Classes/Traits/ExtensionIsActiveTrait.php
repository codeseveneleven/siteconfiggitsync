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

namespace Code711\SiteConfigGitSync\Traits;

use TYPO3\CMS\Core\Core\Environment;

trait ExtensionIsActiveTrait
{
    protected function isActive(): bool
    {
        if (Environment::getContext()->isProduction()) {
            return true;
        }
        if ((int)getenv('SITECONFIGGITSYNC_ENABLE') > 0) {
            return true;
        }
        return false;
    }
}
