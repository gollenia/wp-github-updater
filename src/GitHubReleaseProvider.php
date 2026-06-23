<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final class GitHubReleaseProvider
{
	public function __construct(
		private readonly GitHubRepository $repository,
		private readonly ?string $fallbackTag = null,
	) {
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
			return ReleaseInfo::fromTag($cached);
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

		if (preg_match('~/tag/(v?[0-9A-Za-z._-]+)~', $location, $matches)) {
			$tag = (string) $matches[1];
		}

		if ($tag !== '') {
			set_transient($this->repository->cacheKey(), $tag, HOUR_IN_SECONDS);
		}

		return ReleaseInfo::fromTag($tag);
	}

	public function clearCache(): void
	{
		delete_transient($this->repository->cacheKey());
	}

	private function fallbackRelease(): ReleaseInfo
	{
		$tag = trim($this->fallbackTag ?? '');
		return ReleaseInfo::fromTag($tag);
	}
}

