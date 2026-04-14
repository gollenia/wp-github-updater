<?php

declare(strict_types=1);

namespace Contexis\WpGitHubUpdater;

final readonly class PluginMetadata
{
	public function __construct(
		public string $pluginFile,
		public string $pluginSlug,
		public string $slug,
		public array $data,
	) {
	}

	public static function fromPluginFile(string $pluginFile): self
	{
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$pluginSlug = plugin_basename($pluginFile);

		return new self(
			pluginFile: $pluginFile,
			pluginSlug: $pluginSlug,
			slug: dirname($pluginSlug),
			data: get_plugin_data($pluginFile),
		);
	}
}
