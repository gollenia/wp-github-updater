# wp-github-updater

Small Composer package for WordPress plugin update checks based on GitHub releases.

## Installation

```json
{
  "require": {
    "contexis/wp-github-updater": "^0.2"
  }
}
```

## Quick Start

```php
use Contexis\WpGitHubUpdater\WordPressPluginUpdater;

WordPressPluginUpdater::fromPluginFile(
    pluginFile: __FILE__,
)->registerHooks();
```

This is the intended default API. In the common case you only need:

- the main plugin file path
- a `Plugin URI` header pointing to `https://github.com/<owner>/<repository>`

The release asset name is derived from the main plugin file. For example,
`my-plugin.php` requires the asset `my-plugin.zip`.

You may still pass `owner` and `repositoryName` explicitly when the Plugin URI
does not point at GitHub.

## Release Conventions

The updater checks the latest GitHub release by following the `/releases/latest`
redirect.

If you are also looking for reusable GitHub Actions, see:

- `https://github.com/gollenia/github-actions`

Your repository should use release tags like:

- `v1.2.3`
- `1.2.3`

The download URL for updates is built as:

```text
https://github.com/<owner>/<repo>/releases/download/v<version>/<repo>.zip
```

So your release asset should usually be named `<repo>.zip`.

## What `fallbackVersion` Means

`fallbackVersion` is only a defensive fallback. It is used when GitHub cannot be
reached or the latest release tag cannot be determined.

It is not a separate "target version" and usually should be the currently
installed plugin version. That prevents the updater from reporting a fake update
when GitHub is temporarily unavailable.

If you use the recommended `WordPressPluginUpdater::fromPluginFile(...)` entry
point, you do not need to provide it manually. It is derived from the plugin
header version automatically.

## Lower-Level API

If you want to wire the objects manually, you still can:

```php
use Contexis\WpGitHubUpdater\GitHubReleaseProvider;
use Contexis\WpGitHubUpdater\GitHubRepository;
use Contexis\WpGitHubUpdater\PluginMetadata;
use Contexis\WpGitHubUpdater\WordPressPluginUpdater;

$plugin = PluginMetadata::fromPluginFile(__FILE__);
$repository = GitHubRepository::from('vendor', 'plugin-repo');
$releaseProvider = GitHubReleaseProvider::forPlugin($repository, $plugin);

$updater = new WordPressPluginUpdater(
    plugin: $plugin,
    repository: $repository,
    releaseProvider: $releaseProvider,
);

$updater->registerHooks();
```
