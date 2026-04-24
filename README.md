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
use Contexis\WpGitHubUpdater\WordPressPluginUpdater;

WordPressPluginUpdater::fromPluginFile(
    pluginFile: __FILE__,
    owner: 'vendor',
    repositoryName: 'plugin-repo',
)->registerHooks();
```

For the lower-level API, `fallbackVersion` is optional. If you pass one manually,
it should usually be the currently installed plugin version.
