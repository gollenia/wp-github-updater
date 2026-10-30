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

Your repository should use release tags without a `v` prefix, for example `1.2.3`.

## Releasing a plugin

Install this package in the plugin project and configure its main file in that
project's `composer.json`:

```json
{
  "extra": {
    "wp-github-release": {
      "plugin_file": "my-plugin.php",
      "php_constants": [{"file": "includes/version.php", "constant": "MY_PLUGIN_VERSION"}],
      "build_command": "npm run build"
    }
  }
}
```

The optional `readme.txt`, `package.json`, and `composer.json` version
fields are updated when present. The command checks for a clean Git tree and an
unused tag, then updates only the known version fields, runs the configured
build, commits, and creates an annotated tag:

```bash
vendor/bin/wp-github-release patch
vendor/bin/wp-github-release minor --push
vendor/bin/wp-github-release patch --dry-run
```

`--push` is required to push the commit and tag. Pushing the tag is intended
to trigger the repository's existing GitHub Actions workflow; this tool never
creates a GitHub Release through the API. Use `--root=/path/to/plugin` when
running the binary outside the plugin root.

The download URL for updates is built as:

```text
https://github.com/<owner>/<repo>/releases/download/<version>/<repo>.zip
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
