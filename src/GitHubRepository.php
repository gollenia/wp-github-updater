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

	public static function from(string $owner, string $name): self
	{
		return new self($owner, $name);
	}

	public static function fromPluginUri(string $pluginUri): self
	{
		$parts = parse_url(trim($pluginUri));
		$host = strtolower((string) ($parts['host'] ?? ''));
		$path = trim((string) ($parts['path'] ?? ''), '/');

		if (!in_array($host, ['github.com', 'www.github.com'], true) || $path === '') {
			throw new \InvalidArgumentException(
				'Plugin URI must be a GitHub repository URL, for example https://github.com/owner/repository.'
			);
		}

		$segments = explode('/', $path);
		if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
			throw new \InvalidArgumentException(
				'Plugin URI must identify exactly one GitHub repository, for example https://github.com/owner/repository.'
			);
		}

		return new self($segments[0], preg_replace('/\\.git$/i', '', $segments[1]) ?? $segments[1]);
	}

	public function releasesUrl(): string
	{
		return "https://github.com/{$this->owner}/{$this->name}/releases";
	}

	public function latestReleaseUrl(): string
	{
		return $this->releasesUrl() . '/latest';
	}

	public function downloadPackageUrl(string $version, string $packageName): string
	{
		return "https://github.com/{$this->owner}/{$this->name}/releases/download/{$version}/{$packageName}.zip";
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
