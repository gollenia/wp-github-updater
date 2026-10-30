<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
	$prefix = 'Contexis\\WpGitHubUpdater\\';
	if (str_starts_with($class, $prefix)) {
		require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	}
});

use Contexis\WpGitHubUpdater\Release\Version;
use Contexis\WpGitHubUpdater\Release\ReleaseCommand;

function assertSame(mixed $expected, mixed $actual): void { if ($expected !== $actual) throw new RuntimeException("Expected {$expected}, got {$actual}"); }
function command(string $command, string $cwd): string { exec("cd " . escapeshellarg($cwd) . " && {$command} 2>&1", $out, $code); if ($code !== 0) throw new RuntimeException(implode(PHP_EOL, $out)); return implode(PHP_EOL, $out); }

assertSame('1.2.4', (string) Version::fromString('1.2.3')->bump('patch'));
assertSame('1.3.0', (string) Version::fromString('1.2.3')->bump('minor'));
assertSame('2.0.0', (string) Version::fromString('1.2.3')->bump('major'));
try { Version::fromString('not-semver'); throw new RuntimeException('Invalid version accepted'); } catch (InvalidArgumentException) {}

$root = sys_get_temp_dir() . '/wp-github-release-' . bin2hex(random_bytes(4));
mkdir($root);
file_put_contents("{$root}/plugin.php", "<?php\n/*\nPlugin Name: Test\nVersion: 1.2.3\n*/\n");
file_put_contents("{$root}/readme.txt", "=== Test ===\nStable tag: 1.2.3\n");
file_put_contents("{$root}/package.json", "{\n  \"version\": \"1.2.3\"\n}\n");
file_put_contents("{$root}/version.php", "<?php\nconst TEST_VERSION = '1.2.3';\n");
file_put_contents("{$root}/composer.json", json_encode(['extra' => ['wp-github-release' => [
	'plugin_file' => 'plugin.php',
	'php_constants' => [['file' => 'version.php', 'constant' => 'TEST_VERSION']],
]]], JSON_PRETTY_PRINT) . PHP_EOL);
command('git init -q && git config user.name test && git config user.email test@example.test && git add . && git commit -qm initial', $root);
(new ReleaseCommand())->run('patch', false, false, $root);
assertSame(true, str_contains((string) file_get_contents("{$root}/plugin.php"), 'Version: 1.2.4'));
assertSame(true, str_contains((string) file_get_contents("{$root}/readme.txt"), 'Stable tag: 1.2.4'));
assertSame(true, str_contains((string) file_get_contents("{$root}/version.php"), "TEST_VERSION = '1.2.4'"));
assertSame(true, str_contains((string) file_get_contents("{$root}/package.json"), '1.2.4'));
assertSame('1.2.4', trim(command('git tag --list 1.2.4', $root)));
file_put_contents("{$root}/dirty.txt", 'dirty');
try {
	(new ReleaseCommand())->run('patch', false, false, $root);
	throw new RuntimeException('Dirty tree accepted');
} catch (RuntimeException $error) {
	assertSame(true, str_contains($error->getMessage(), 'not clean'));
}

echo "10 assertions passed\n";
