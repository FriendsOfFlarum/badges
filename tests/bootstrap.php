<?php

// Prefer a standalone package install, then a containing workbench installation.
// Only Composer is loaded: never boot Flarum or read its database configuration.
$package = dirname(__DIR__);
$directory = $package;
do {
    $autoload = $directory.'/vendor/autoload.php';
    if (is_file($autoload)) {
        $loader = require $autoload;
        // Workbench installs need not have this extension in their Composer map.
        $loader->addPsr4('FoF\\Badges\\', $package.'/src/', true);
        $loader->addPsr4('FoF\\Badges\\Tests\\', __DIR__.'/', true);

        return $loader;
    }
    $parent = dirname($directory);
    if ($parent === $directory) {
        break;
    }
    $directory = $parent;
} while (true);

throw new RuntimeException('Cannot find vendor/autoload.php in the package or a containing workspace.');
