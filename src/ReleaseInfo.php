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
}
