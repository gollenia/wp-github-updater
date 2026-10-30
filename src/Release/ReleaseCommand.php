<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater\Release;

final class ReleaseCommand
{
	public function run(string $part, bool $push, bool $dryRun, string $root): int
	{
		$config = ProjectConfiguration::fromRoot($root);
		$files = new VersionFiles($config);
		$git = new Git($config->root);
		$current = $files->currentVersion();
		$next = $current->bump($part);
		$tag = $config->tagPrefix . $next;
		$git->ensureTagMissing($tag);

		if ($dryRun) {
			echo "Would release {$current} as {$next}\n";
			echo "Plugin: {$config->pluginFile}\n";
			echo "Tag: {$tag}\n";
			echo $config->buildCommand ? "Build: {$config->buildCommand}\n" : "Build: none\n";
			echo $push ? "Push: yes\n" : "Push: no\n";
			return 0;
		}

		$git->ensureClean();

		try {
			$changed = $files->update($next);
			if ($config->buildCommand !== null && $config->buildCommand !== '') {
				chdir($config->root);
				passthru($config->buildCommand, $exitCode);
				if ($exitCode !== 0) throw new \RuntimeException("Build failed with exit code {$exitCode}.");
			}
			$git->commitTagAndPush($tag, $changed, $push);
			echo "Released {$next}" . ($push ? ' and pushed.' : '.') . PHP_EOL;
			return 0;
		} catch (\Throwable $error) {
			$files->restore();
			throw $error;
		}
	}
}
