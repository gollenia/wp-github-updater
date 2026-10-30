<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater\Release;

final readonly class Version
{
	public function __construct(
		public int $major,
		public int $minor,
		public int $patch,
	) {
	}

	public static function fromString(string $version): self
	{
		if (!preg_match('/^(?<major>0|[1-9]\\d*)\\.(?<minor>0|[1-9]\\d*)\\.(?<patch>0|[1-9]\\d*)(?:-[0-9A-Za-z.-]+)?(?:\\+[0-9A-Za-z.-]+)?$/', trim($version), $matches)) {
			throw new \InvalidArgumentException("Invalid semantic version: {$version}");
		}

		return new self((int) $matches['major'], (int) $matches['minor'], (int) $matches['patch']);
	}

	public function bump(string $part): self
	{
		return match ($part) {
			'patch' => new self($this->major, $this->minor, $this->patch + 1),
			'minor' => new self($this->major, $this->minor + 1, 0),
			'major' => new self($this->major + 1, 0, 0),
			default => throw new \InvalidArgumentException("Version part must be patch, minor, or major; got {$part}."),
		};
	}

	public function __toString(): string
	{
		return "{$this->major}.{$this->minor}.{$this->patch}";
	}
}
