# wp-github-updater

Small Composer package for WordPress plugin update checks based on GitHub releases.

## Installation

```json
{
  "require": {
    "contexis/wp-github-updater": "^0.1"
  }
}
```

## Usage

```php
use Contexis\WpGitHubUpdater\GitHubReleaseProvider;
use Contexis\WpGitHubUpdater\GitHubRepository;
use Contexis\WpGitHubUpdater\PluginMetadata;
use Contexis\WpGitHubUpdater\WordPressPluginUpdater;

$plugin = PluginMetadata::fromPluginFile(__FILE__);
$repository = new GitHubRepository('vendor', 'plugin-repo');
$releaseProvider = new GitHubReleaseProvider($repository, (string) $plugin->data['Version']);

(new WordPressPluginUpdater(
    plugin: $plugin,
    repository: $repository,
    releaseProvider: $releaseProvider,
))->registerHooks();
```
