<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final class GitHubReleaseProvider
{
	public function __construct(
		private readonly GitHubRepository $repository,
		private readonly string $fallbackVersion,
	) {
	}

	public function getLatestRelease(): ReleaseInfo
	{
		$cached = get_transient($this->repository->cacheKey());
		if (is_string($cached) && $cached !== '') {
			return new ReleaseInfo(version: $cached, tag: 'v' . $cached);
		}

		$response = wp_remote_get($this->repository->latestReleaseUrl(), [
			'redirection' => 0,
			'timeout' => 5,
		]);

		if (is_wp_error($response) || !isset($response['headers']['location'])) {
			return new ReleaseInfo(
				version: $this->fallbackVersion,
				tag: 'v' . $this->fallbackVersion,
			);
		}

		$location = (string) $response['headers']['location'];
		$tag = 'v' . $this->fallbackVersion;
		$version = $this->fallbackVersion;

		if (preg_match('~/tag/(v?\d+\.\d+\.\d+)~', $location, $matches)) {
			$tag = (string) $matches[1];
			$version = ltrim($tag, 'v');
		}

		set_transient($this->repository->cacheKey(), $version, HOUR_IN_SECONDS);

		return new ReleaseInfo(version: $version, tag: $tag);
	}

	public function clearCache(): void
	{
		delete_transient($this->repository->cacheKey());
	}
}
