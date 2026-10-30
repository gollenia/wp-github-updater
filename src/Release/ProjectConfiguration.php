<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater\Release;

final readonly class ProjectConfiguration
{
	/**
	 * @param list<array{file: string, constant: string}> $phpConstants
	 */
	public function __construct(
		public string $root,
		public string $pluginFile,
		public ?string $readmeFile,
		public ?string $packageFile,
		public ?string $composerFile,
		public array $phpConstants,
		public ?string $buildCommand,
		public string $tagPrefix,
	) {
	}

	public static function fromRoot(string $root): self
	{
		$root = realpath($root) ?: throw new \RuntimeException("Project root does not exist: {$root}");
		$composerFile = "{$root}/composer.json";
		$composer = self::readJson($composerFile);
		$config = $composer['extra']['wp-github-release'] ?? [];
		if (!is_array($config)) {
			throw new \RuntimeException('composer.json extra.wp-github-release must be an object.');
		}

		$plugin = $config['plugin_file'] ?? self::discoverPluginFile($root);
		if (!is_string($plugin) || $plugin === '') {
			throw new \RuntimeException('Set extra.wp-github-release.plugin_file in composer.json.');
		}

		$constants = $config['php_constants'] ?? [];
		if (!is_array($constants)) {
			throw new \RuntimeException('extra.wp-github-release.php_constants must be an array.');
		}

		$normalizedConstants = [];
		foreach ($constants as $constant) {
			if (!is_array($constant) || !is_string($constant['file'] ?? null) || !is_string($constant['constant'] ?? null)) {
				throw new \RuntimeException('Each php_constants entry needs file and constant.');
			}
			$normalizedConstants[] = ['file' => $constant['file'], 'constant' => $constant['constant']];
		}

		return new self(
			root: $root,
			pluginFile: self::path($root, $plugin),
			readmeFile: self::optionalPath($root, $config['readme_file'] ?? 'readme.txt'),
			packageFile: self::optionalPath($root, $config['package_file'] ?? 'package.json'),
			composerFile: is_file($composerFile) ? $composerFile : null,
			phpConstants: $normalizedConstants,
			buildCommand: is_string($config['build_command'] ?? null) ? $config['build_command'] : null,
			tagPrefix: is_string($config['tag_prefix'] ?? null) ? $config['tag_prefix'] : '',
		);
	}

	private static function discoverPluginFile(string $root): ?string
	{
		foreach (glob("{$root}/*.php") ?: [] as $file) {
			$contents = file_get_contents($file);
			if ($contents !== false && preg_match('/^\s*\*?\s*Version:\s*.+$/mi', $contents)) {
				return basename($file);
			}
		}

		return null;
	}

	private static function readJson(string $file): array
	{
		if (!is_file($file)) {
			return [];
		}

		$json = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($json)) {
			throw new \RuntimeException("Expected JSON object in {$file}.");
		}

		return $json;
	}

	private static function optionalPath(string $root, mixed $path): ?string
	{
		return is_string($path) && $path !== '' && is_file(self::path($root, $path))
			? self::path($root, $path)
			: null;
	}

	private static function path(string $root, string $path): string
	{
		return str_starts_with($path, '/') ? $path : "{$root}/{$path}";
	}
}
