<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final readonly class GitHubRepository
{
	public function __construct(
		public string $owner,
		public string $name,
	) {
	}

	public function releasesUrl(): string
	{
		return "https://github.com/{$this->owner}/{$this->name}/releases";
	}

	public function latestReleaseUrl(): string
	{
		return $this->releasesUrl() . '/latest';
	}

	public function downloadPackageUrl(string $version): string
	{
		return "https://github.com/{$this->owner}/{$this->name}/releases/download/v{$version}/{$this->name}.zip";
	}

	public function downloadSourceUrl(string $tag): string
	{
		return "https://github.com/{$this->owner}/{$this->name}/archive/refs/tags/{$tag}.zip";
	}

	public function cacheKey(): string
	{
		return 'ghup_' . md5($this->owner . '/' . $this->name);
	}
}
