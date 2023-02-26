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

namespace Code711\SiteConfigGitSync\Interfaces;

interface GitApiServiceInterface
{
    public function getHost(): string;
    public function getProject(): string;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getBranches(): array;

    public function hasBranch(string $branch): bool;
    /**
     * @return array<string,mixed>|null
     */
    public function getBranch(string $branch): ?array;
    public function getBranchName(string $identifier): string;

    /**
     * @return array<string,string>
     */
    public function getAuthor(): array;
    public function getCommitMessage(string $identifier, string $addtional_info = ''): string;
    public function getMergeRequestMessage(string $identifier, string $addtional_info = ''): string;

    public function createBranch(string $newbranch): bool;
    public function moveFile(string $oldfilename, string $newfilename, string $commitmessage, string $branch): bool;
    public function deleteFile(string $filename, string $branch, string $commitmessage): bool;
    public function commitFile(string $filename, string $filecontent, string $commitmessage, string $branch): bool;
    public function createMergeRequest(string $identifier, string $branch, string $additional_info=''): void;

    /**
     * @return array<int,array<string,string>>
     */
    public function getMembers(): array;
}
