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

$EM_CONF['siteconfiggitsync'] = [
    'title' => '(Code711) Site Config Git Sync',
    'description' => 'This extension will push changes to the site config yaml files back to your GitLAB or GitHUB repository by creating a branch for the changes along with a merge-request. It is targeted towards automated CI/CD installations or sites in general where the site-config is git versioned. No local git binary or .git directory is needed. Requires EXT:siteconfigurationevents',
    'category' => 'plugin',
    'version' => '2.1.2',
    'state' => 'stable',
    'clearcacheonload' => 1,
    'author' => 'Frank Berger',
    'author_email' => 'fberger@code711.de',
    'author_company' => 'Code711, a label of Sudhaus7, B-Factor GmbH and 12bis3 GbR',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
            'siteconfigurationevents' => '2.0.0-2.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Code711\\SiteConfigGitSync\\' => 'Classes',
        ],
    ],
];
