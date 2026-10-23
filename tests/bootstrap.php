<?php

/*
 * This file is part of fof/badges
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

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
