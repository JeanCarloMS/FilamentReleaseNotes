<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Infrastructure\Git;

use JeanCarloMS\FilamentReleaseNotes\Domain\VO\GitHubRepositoryVO;

final class GitRemoteUrlParser
{
    public function parse(?string $remoteUrl): ?GitHubRepositoryVO
    {
        if (! is_string($remoteUrl) || $remoteUrl === '') {
            return null;
        }

        $normalizedUrl = preg_replace('/\.git$/', '', trim($remoteUrl));

        if (! is_string($normalizedUrl)) {
            return null;
        }

        if (preg_match('/^git@github\.com:(?<owner>[^\/]+)\/(?<repo>.+)$/', $normalizedUrl, $matches) !== 1) {
            if (preg_match('/^https:\/\/github\.com\/(?<owner>[^\/]+)\/(?<repo>.+)$/', $normalizedUrl, $matches) !== 1) {
                return null;
            }
        }

        return new GitHubRepositoryVO(
            owner: $matches['owner'],
            repository: $matches['repo'],
            baseUrl: sprintf('https://github.com/%s/%s', $matches['owner'], $matches['repo']),
        );
    }
}
