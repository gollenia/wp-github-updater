<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final class GitHubReleaseProvider
{
	public function __construct(
		private readonly GitHubRepository $repository,
		private readonly ?string $fallbackVersion = null,
	) {
	}

	public static function from(
		GitHubRepository $repository,
		?string $fallbackVersion = null,
	): self {
		return new self($repository, $fallbackVersion);
	}

	public static function forPlugin(
		GitHubRepository $repository,
		PluginMetadata $plugin,
	): self {
		return new self(
			$repository,
			(string) ($plugin->data['Version'] ?? ''),
		);
	}

	public function getLatestRelease(): ReleaseInfo
	{
		$cached = get_transient($this->repository->cacheKey());
		if (is_string($cached) && $cached !== '') {
			$version = self::normalizeVersion($cached);

			return new ReleaseInfo(version: $version, tag: 'v' . $version);
		}

		$response = wp_remote_get($this->repository->latestReleaseUrl(), [
			'redirection' => 0,
			'timeout' => 5,
		]);

		if (is_wp_error($response) || !isset($response['headers']['location'])) {
			return $this->fallbackRelease();
		}

		$location = (string) $response['headers']['location'];
		$fallback = $this->fallbackRelease();
		$tag = $fallback->tag;
		$version = $fallback->version;

		if (preg_match('~/tag/(v?[0-9A-Za-z._-]+)~', $location, $matches)) {
			$tag = (string) $matches[1];
			$version = self::normalizeVersion($tag);
		}

		if ($version !== '') {
			set_transient($this->repository->cacheKey(), $version, HOUR_IN_SECONDS);
		}

		return new ReleaseInfo(version: $version, tag: $tag);
	}

	public function clearCache(): void
	{
		delete_transient($this->repository->cacheKey());
	}

	private function fallbackRelease(): ReleaseInfo
	{
		$version = self::normalizeVersion($this->fallbackVersion ?? '');

		return new ReleaseInfo(
			version: $version,
			tag: $version === '' ? '' : 'v' . $version,
		);
	}

	private static function normalizeVersion(string $version): string
	{
		return ltrim(trim($version), 'v');
	}
}
