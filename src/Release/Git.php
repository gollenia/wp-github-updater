<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater\Release;

final readonly class Git
{
	public function __construct(private string $root)
	{
	}

	public function ensureClean(): void
	{
		if ($this->run(['status', '--porcelain']) !== '') throw new \RuntimeException('Git working tree is not clean.');
	}

	public function ensureTagMissing(string $tag): void
	{
		[$code] = $this->execute(['rev-parse', '-q', '--verify', "refs/tags/{$tag}"]);
		if ($code === 0) throw new \RuntimeException("Tag already exists: {$tag}");
	}

	public function commitTagAndPush(string $version, array $files, bool $push): void
	{
		$this->run(array_merge(['add', '--'], $files));
		$this->run(['commit', '-m', "Release version {$version}"]);
		$this->run(['tag', '-a', $version, '-m', "Release version {$version}"]);
		if (!$push) return;
		$branch = $this->run(['branch', '--show-current']);
		$this->run(['push', 'origin', $branch]);
		$this->run(['push', 'origin', $version]);
	}

	public function run(array $arguments): string
	{
		[$code, $output] = $this->execute($arguments);
		if ($code !== 0) throw new \RuntimeException("git " . implode(' ', $arguments) . " failed: {$output}");
		return trim($output);
	}

	/** @return array{int, string} */
	private function execute(array $arguments): array
	{
		$command = array_merge(['git'], $arguments);
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
		if (!is_resource($process)) throw new \RuntimeException('Cannot start git.');
		$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		return [proc_close($process), $output];
	}
}
