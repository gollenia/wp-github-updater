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
		?string $owner = null,
		?string $repositoryName = null,
	): self {
		$plugin = PluginMetadata::fromPluginFile($pluginFile);
		$repository = ($owner === null && $repositoryName === null)
			? GitHubRepository::fromPluginUri((string) ($plugin->data['PluginURI'] ?? ''))
			: GitHubRepository::from(
				$owner ?? throw new \InvalidArgumentException('Both GitHub owner and repository name must be provided together.'),
				$repositoryName ?? throw new \InvalidArgumentException('Both GitHub owner and repository name must be provided together.'),
			);

		return new self(
			plugin: $plugin,
			repository: $repository,
			releaseProvider: GitHubReleaseProvider::forPlugin($repository, $plugin),
		);
	}

	public function registerHooks(): void
	{
		add_filter('site_transient_update_plugins', [$this, 'checkForUpdate']);
		add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
		add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
		add_filter('plugin_action_links_' . $this->plugin->pluginSlug, [$this, 'actionLinks']);
		add_action('rest_api_init', [$this, 'registerRestRoute']);
		register_activation_hook($this->plugin->pluginFile, function (): void {
			$this->releaseProvider->clearCache();
		});
	}

	public function registerRestRoute(): void
	{
		register_rest_route('wp-github-updater/v1', '/check/' . $this->plugin->slug, [
			'methods' => 'GET',
			'permission_callback' => static fn (): bool => current_user_can('update_plugins'),
			'callback' => [$this, 'checkUpdates'],
		]);
	}

	/** @return array{version: string, update_available: bool} */
	public function checkUpdates(): array
	{
		$this->releaseProvider->clearCache();
		delete_site_transient('update_plugins');
		wp_update_plugins();
		$updates = get_site_transient('update_plugins');
		$update = is_object($updates) ? ($updates->response[$this->plugin->pluginSlug] ?? null) : null;

		return [
			'version' => is_object($update) ? (string) $update->new_version : (string) ($this->plugin->data['Version'] ?? ''),
			'update_available' => is_object($update),
		];
	}

	/** @param array<string, string> $links */
	public function actionLinks(array $links): array
	{
		$url = add_query_arg(
			'_wpnonce',
			wp_create_nonce('wp_rest'),
			rest_url('wp-github-updater/v1/check/' . $this->plugin->slug),
		);
		$links['check_updates'] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates') . '</a>';

		return $links;
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
		return $this->addReleaseToTransient($transient, $release);
	}

	private function addReleaseToTransient(mixed $transient, ReleaseInfo $release): mixed
	{
		if (!is_object($transient) || !isset($transient->checked)) {
			return $transient;
		}

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
			'package' => $this->repository->downloadPackageUrl($release->version, $this->plugin->packageName),
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

		$release = $this->releaseProvider->refreshLatestRelease();
		if ($release->version === '') {
			return $result;
		}

		$updates = get_site_transient('update_plugins');
		$updates = $this->addReleaseToTransient($updates, $release);
		if (is_object($updates)) {
			set_site_transient('update_plugins', $updates);
		}

		$info = new \stdClass();
		$info->name = basename(dirname($this->plugin->pluginFile));
		$info->slug = $this->plugin->slug;
		$info->version = $release->version;
		$info->author = (string) ($this->plugin->data['Author'] ?? '');
		$info->homepage = $this->repository->releasesUrl();
		$info->download_link = $this->repository->downloadSourceUrl($release->tag);
		$info->sections = [
			'description' => (string) $this->plugin->data['Description'],
			'changelog' => $this->changelog($release),
		];

		return $info;
	}

	private function changelog(ReleaseInfo $release): string
	{
		$cacheKey = $this->repository->cacheKey() . '_changelog_' . md5($release->tag);
		$changelog = get_transient($cacheKey);

		if (!is_string($changelog)) {
			$response = wp_remote_get($this->repository->changelogUrl($release->tag), ['timeout' => 5]);
			$changelog = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
			set_transient($cacheKey, $changelog, HOUR_IN_SECONDS);
		}

		if ($changelog === '') {
			return 'See changelog on <a href="' . esc_url($this->repository->releasesUrl()) . '">GitHub</a>.';
		}

		return '<pre>' . esc_html($changelog) . '</pre>';
	}
}
