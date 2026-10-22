<?php

// Deliberately independent of application config and inherited database settings.
require dirname(__DIR__).'/bootstrap.php';

$root = dirname(__DIR__, 2);
$tmp = __DIR__.'/tmp/isolated-api';
$database = $tmp.'/api-verification.sqlite';
if (realpath(__DIR__) !== $root.'/tests/integration') {
    throw new RuntimeException('Integration tests must run inside this checkout.');
}
if (! file_exists($tmp) && ! mkdir($tmp, 0777, true)) {
    throw new RuntimeException('Cannot create the isolated integration test directory.');
}
if (realpath($tmp) !== $root.'/tests/integration/tmp/isolated-api' || is_link($database) || is_link($tmp.'/config.php')) {
    throw new RuntimeException('Integration test paths must stay inside this checkout.');
}

foreach ([
    'DB_DRIVER' => 'sqlite',
    'DB_DATABASE' => $database,
    'DB_HOST' => 'localhost',
    'DB_PORT' => '0',
    'DB_USERNAME' => 'isolated-test',
    'DB_PASSWORD' => '',
    'DB_PREFIX' => '',
    'FLARUM_TEST_TMP_DIR' => $tmp,
    'FLARUM_TEST_TMP_DIR_LOCAL' => $tmp,
    'FLARUM_TEST_VENDOR_PATH' => $root.'/vendor',
] as $key => $value) {
    putenv($key.'='.$value);
}

if (! is_file($tmp.'/config.php')) {
    if (! is_file($database)) {
        // DatabaseConfig::prepare otherwise prepends the base path to this
        // absolute filename. Create only this validated test fixture first.
        if (! touch($database)) {
            throw new RuntimeException('Cannot create the isolated SQLite test fixture.');
        }
    }
    // SetupScript prints connection credentials, even for SQLite. Suppress them.
    ob_start();
    try {
        (new Flarum\Testing\integration\Setup\SetupScript())->run();
    } finally {
        ob_end_clean();
    }
}

$config = require $tmp.'/config.php';
if (($config['database']['driver'] ?? null) !== 'sqlite'
    || ($config['database']['database'] ?? null) !== $database) {
    throw new RuntimeException('Refusing a non-isolated integration test database.');
}
