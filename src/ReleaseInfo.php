<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final readonly class ReleaseInfo
{
	public function __construct(
		public string $version,
		public string $tag,
	) {
	}

	public function fromTag(string $tag): self
	{
		return new self(
			version: self::normalizeVersion($tag),
			tag: $tag,
		);
	}

	private static function normalizeVersion(string $version): string
	{
		return ltrim(trim($version), 'v');
	}
}

