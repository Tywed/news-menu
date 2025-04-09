<?php

namespace Tywed\Webtrees\Module\NewsMenu;

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Tywed\\Webtrees\\Module\\NewsMenu\\', __DIR__ . '/src');
$loader->addPsr4('Tywed\\Webtrees\\Module\\NewsMenu\\Migrations\\', __DIR__ . '/Migrations');

$loader->register();
