<?php

namespace Tywed\Webtrees\Module\NewsMenu;

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Tywed\\Webtrees\\Module\\NewsMenu\\', __DIR__);
$loader->addPsr4('Tywed\\Webtrees\\Module\\NewsMenu\\', __DIR__. '/Migrations');

$loader->register();
