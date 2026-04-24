<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final class WordPressPluginUpdater
{
	public function __construct(
		private readonly PluginMetadata $plugin,
		private readonly GitHubRepository $repository,
		private readonly GitHubReleaseProvider $releaseProvider,
	) {
	}

	public static function fromPluginFile(
		string $pluginFile,
		string $owner,
		string $repositoryName,
	): self {
		$plugin = PluginMetadata::fromPluginFile($pluginFile);
		$repository = GitHubRepository::from($owner, $repositoryName);

		return new self(
			plugin: $plugin,
			repository: $repository,
			releaseProvider: GitHubReleaseProvider::forPlugin($repository, $plugin),
		);
	}

	public function registerHooks(): void
	{
		add_filter('site_transient_update_plugins', [$this, 'checkForUpdate']);
		add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
		register_activation_hook($this->plugin->pluginFile, function (): void {
			$this->releaseProvider->clearCache();
		});
	}

	public function checkForUpdate(mixed $transient): mixed
	{
		if (!is_object($transient) || !isset($transient->checked)) {
			return $transient;
		}

		if (!array_key_exists($this->plugin->pluginSlug, (array) $transient->checked)) {
			return $transient;
		}

		$release = $this->releaseProvider->getLatestRelease();
		$currentVersion = (string) $transient->checked[$this->plugin->pluginSlug];

		if (!version_compare($release->version, $currentVersion, '>')) {
			return $transient;
		}

		$data = $this->plugin->data;
		$transient->response[$this->plugin->pluginSlug] = (object) [
			'slug' => $this->plugin->slug,
			'plugin' => $this->plugin->pluginSlug,
			'new_version' => $release->version,
			'url' => (string) ($data['PluginURI'] ?? ''),
			'package' => $this->repository->downloadPackageUrl($release->version),
			'requires' => (string) ($data['RequiresWP'] ?? ''),
			'tested' => (string) ($data['Tested'] ?? ''),
			'last_updated' => (string) ($data['LastUpdated'] ?? ''),
			'requires_php' => (string) ($data['RequiresPHP'] ?? ''),
		];

		return $transient;
	}

	public function pluginInfo(mixed $result, string $action, object $args): mixed
	{
		if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->plugin->slug) {
			return $result;
		}

		$release = $this->releaseProvider->getLatestRelease();
		if ($release->version === '') {
			return $result;
		}

		$info = new \stdClass();
		$info->name = basename(dirname($this->plugin->pluginFile));
		$info->slug = $this->plugin->slug;
		$info->version = $release->version;
		$info->author = (string) ($this->plugin->data['Author'] ?? '');
		$info->homepage = $this->repository->releasesUrl();
		$info->download_link = $this->repository->downloadSourceUrl($release->tag);
		$info->sections = [
			'description' => $this->readDescription(),
			'changelog' => 'See changelog on <a href="' . esc_url($this->repository->releasesUrl()) . '">GitHub</a>',
		];

		return $info;
	}

	private function readDescription(): string
	{
		$basePath = dirname($this->plugin->pluginFile);
		$candidates = [
			$basePath . '/readme.md',
			$basePath . '/README.md',
			$basePath . '/readme.txt',
			$basePath . '/README.txt',
		];

		foreach ($candidates as $candidate) {
			if (is_readable($candidate)) {
				$content = file_get_contents($candidate);

				return is_string($content) ? $content : '';
			}
		}

		return '';
	}
}
