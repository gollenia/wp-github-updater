<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater\Release;

final class VersionFiles
{
	/** @var array<string, string> */
	private array $original = [];

	public function __construct(private readonly ProjectConfiguration $config)
	{
	}

	public function currentVersion(): Version
	{
		$contents = $this->read($this->config->pluginFile);
		if (!preg_match('/^(?<prefix>\s*\*?\s*Version:\s*)(?<version>[^\s]+)(?<suffix>\s*)$/mi', $contents, $matches)) {
			throw new \RuntimeException("Plugin header Version not found in {$this->config->pluginFile}.");
		}

		return Version::fromString($matches['version']);
	}

	/** @return list<string> */
	public function update(Version $version): array
	{
		$changed = [];
		$target = (string) $version;
		$this->replace($this->config->pluginFile, '/^(\s*\*?\s*Version:\s*)[^\s]+(\s*)$/mi', '${1}' . $target . '${2}');
		$changed[] = $this->config->pluginFile;

		if ($this->config->readmeFile !== null) {
			if ($this->replace($this->config->readmeFile, '/^(Stable tag:\s*)\S+(\s*)$/mi', '${1}' . $target . '${2}', false)) $changed[] = $this->config->readmeFile;
		}

		foreach ([$this->config->packageFile, $this->config->composerFile] as $file) {
			if ($file !== null) {
				if ($this->updateJsonVersion($file, $target)) $changed[] = $file;
			}
		}

		foreach ($this->config->phpConstants as $constant) {
			$file = $this->config->root . '/' . $constant['file'];
			$name = preg_quote($constant['constant'], '/');
			if ($this->replace($file, '/(const\s+' . $name . '\s*=\s*[\'"])[^\'"]+([\'"]\s*;)/', '$1' . $target . '$2', false)) $changed[] = $file;
		}

		return array_values(array_filter($changed));
	}

	public function restore(): void
	{
		foreach ($this->original as $file => $contents) {
			file_put_contents($file, $contents);
		}
	}

	private function updateJsonVersion(string $file, string $version): bool
	{
		$contents = $this->read($file);
		$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($data) || !array_key_exists('version', $data)) {
			return false;
		}
		$data['version'] = $version;
		$this->write($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
		return true;
	}

	private function replace(string $file, string $pattern, string $replacement, bool $required = true): bool
	{
		$contents = $this->read($file);
		$replacement = str_replace(['$1', '$2'], ['${1}', '${2}'], $replacement);
		$updated = preg_replace($pattern, $replacement, $contents, 1, $count);
		if ($updated === null || ($count === 0 && $required)) {
			throw new \RuntimeException("Expected version field not found in {$file}.");
		}
		if ($count === 0) return false;
		$this->write($file, $updated);
		return true;
	}

	private function read(string $file): string
	{
		if (!is_file($file)) throw new \RuntimeException("File not found: {$file}");
		$contents = file_get_contents($file);
		if ($contents === false) throw new \RuntimeException("Cannot read {$file}");
		$this->original[$file] ??= $contents;
		return $contents;
	}

	private function write(string $file, string $contents): void
	{
		if (file_put_contents($file, $contents) === false) throw new \RuntimeException("Cannot write {$file}");
	}
}
