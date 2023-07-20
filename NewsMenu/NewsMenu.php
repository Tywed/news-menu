<?php

/*
Copyright (C) 2023 Tywed

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// This module adds the "News" item to the main menu.

declare(strict_types=1);

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Registry;

class NewsMenu extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleGlobalTrait;

    // The name of this module.
    public const CUSTOM_MODULE = 'NewsMenu';

    // The author of this module.
    public const CUSTOM_AUTHOR = 'Tywed';

    // The website of this module.
    public const CUSTOM_WEBSITE = 'https://github.com/' . self::CUSTOM_MODULE . '/';

    // The version of this module.
    public const CUSTOM_VERSION = '0.1.0';

    // The URL of the latest version of this module.
    public const CUSTOM_LAST = self::CUSTOM_WEBSITE . 'raw/main/latest-version.txt';

    // The URL of the support forum for this module.
    public const CUSTOM_SUPPORT_URL = self::CUSTOM_WEBSITE . 'issues';

    // The title of this module.
    public function title(): string
    {
        return I18N::translate('News menu');
    }

    // The description of this module.
    public function description(): string
    {
        return I18N::translate('Add an extra item to the main menu as a link to a webtrees news.');
    }

    // The author's name.
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    // The version of this module.
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    // The URL of the latest version of this module.
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    // The URL of the support forum for this module.
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

     // This method is called when the module is loaded.
     public function boot(): void
     {
         View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
     }
 
     // The folder where the module's resources are located.
     public function resourcesFolder(): string
     {
         return __DIR__ . '/resources/';
     }
 
     // The default order of the module in the main menu.
     public function defaultMenuOrder(): int
     {
         return -1;
     }
 
     // Returns a menu item for the module.
     public function getMenu(Tree $tree): ?Menu
     {
         // Returns a menu item for the module, or null if the module should not be displayed in the menu.
         if ($tree === null) {
             return '';
         }
 
         $url = route('module', [
             'module' => $this->name(),
             'action' => 'Page',
             'tree'   => $tree ? $tree->name() : null,
         ]);
 
         $menu_title = I18N::translate('News');
 
         return new Menu($menu_title, e($url), 'news-menu');
     }
 
     // Returns the page content for the module.
     public function getPageAction(ServerRequestInterface $request): ResponseInterface
     {
         // Returns the page content for the module.
         $page = '::page-news';
 
         $tree = $request->getAttribute('tree');
         assert($tree instanceof Tree);
 
         $articles = DB::table('news')
         ->where('gedcom_id', '=', $tree->id())
         ->orderByDesc('updated')
         ->get()
         ->map(static function (object $row): object {
             $row->updated = Registry::timestampFactory()->fromString($row->updated);
 
             return $row;
         });
 
         return $this->viewResponse($this->name() . $page, [
             'title' => $this->title(),
             'tree'  => $request->getAttribute('tree'),
             'articles' => $articles,
             'limit'    => 5,
         ]);
     }
 
     // Returns the CSS content for the module.
     public function headContent(): string
     {
         $url = $this->assetUrl('css/news-menu.css');
 
         return '<link rel="stylesheet" href="' . e($url) . '">';
     }
 }
 
