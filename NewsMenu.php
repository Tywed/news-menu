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

namespace Tywed\Webtrees\Module\NewsMenu;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Services\HtmlService;
use Illuminate\Database\Query\Expression;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\FlashMessages;
use Carbon\Carbon;


class NewsMenu extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleGlobalInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleGlobalTrait;
    use ModuleConfigTrait;

    public const CUSTOM_MODULE = 'News-Menu';
    public const CUSTOM_AUTHOR = 'Tywed';
    public const CUSTOM_WEBSITE = 'https://github.com/tywed/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION = '0.2.0';
    public const CUSTOM_LAST = self::CUSTOM_WEBSITE . 'raw/main/latest-version.txt';
    public const CUSTOM_SUPPORT_URL = self::CUSTOM_WEBSITE . 'issues';
    public const SCHEMA_VERSION = 1;
    public const SETTING_SCHEMA_NAME = 'NEWS_SCHEMA_VERSION';

    public function title(): string
    {
        return I18N::translate('News');
    }

    public function description(): string
    {
        return I18N::translate('Add an extra item to the main menu as a link to a webtrees news.');
    }

    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        app(MigrationService::class)->updateSchema('Tywed\Webtrees\Module\NewsMenu\Migrations', self::SETTING_SCHEMA_NAME, self::SCHEMA_VERSION);
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . "langs/{$language}.php";

        return file_exists($file)
            ? require $file
            : require $this->resourcesFolder() . 'langs/en.php';
    }

    public function defaultMenuOrder(): int
    {
        return (int)$this->getPreference('news_menu_order', '-1');
    }

    // Returns a menu item for the module.
    public function getMenu(Tree $tree): ?Menu
    {
        // Returns a menu item for the module, or null if the module should not be displayed in the menu.
        if ($tree === null) {
            return '';
        }

        $url = route('module', [
            'tree' => $tree ? $tree->name() : null,
            'module' => $this->name(),
            'action' => 'Page',
        ]);

        $menu_title = I18N::translate('News');

        return new Menu($menu_title, e($url), 'news-menu');
    }

    // Returns the CSS content for the module.
    public function headContent(): string
    {
        $url = $this->assetUrl('css/news-menu.css');

        return '<link rel="stylesheet" href="' . e($url) . '">';
    }

    public function getPageAction(ServerRequestInterface $request): ResponseInterface
    {
        // Returns the page content for the module.
        $page = '::page-news';

        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $perPage = isset($_GET['limit']) ? intval($_GET['limit']) : (int)$this->getPreference('limit_news', $default = '5');
        $offset = ($currentPage - 1) * $perPage;

        $totalArticles = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->count();

        $articles = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->orderByDesc('updated')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        if (!Auth::isManager($tree)) {
            $articles = $articles->filter(function ($article) {
                return $article->updated <= date('Y-m-d H:i:s');
            });
        }

        $articles = $articles->map(function ($article) use ($tree) {
            $media_id = Registry::mediaFactory()->make($article->media_id, $tree);
            $updated = Registry::timestampFactory()->fromString($article->updated);
            $article->media_id = $media_id;
            $article->updated = $updated;
            return $article;
        });

        return $this->viewResponse($this->name() . $page, [
            'title' => $this->title(),
            'tree' => $tree,
            'articles' => $articles,
            'limit' => $perPage,
            'totalArticles' => $totalArticles,
            'current' => $currentPage,
        ]);
    }

    public function getEditNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        if (!Auth::isManager($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news_id = Validator::queryParams($request)->integer('news_id', 0);

        if ($news_id !== 0) {
            $row = DB::table('news')
                ->where('news_id', '=', $news_id)
                ->where('gedcom_id', '=', $tree->id())
                ->first();

            $media = Registry::mediaFactory()->make($row->media_id, $tree);

            if ($row === null) {
                throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
            }
        } else {
            $row = (object) [
                'body'    => '',
                'subject' => '',
                'brief' => '',
                'updated' => ''
            ];

            $media = '';
        }

        $title = I18N::translate('Add/edit a journal/news entry');

        return $this->viewResponse($this->name() . '::edit', [
            'news_id' => $news_id,
            'updated' => $row->updated,
            'subject' => $row->subject,
            'brief' => $row->brief,
            'body'    => $row->body,
            'media' => $media,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    private HtmlService $html_service;

    public function __construct()
    {
        $this->html_service = new HtmlService();
    }

    public function postEditNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        if (!Auth::isManager($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news_id = Validator::queryParams($request)->integer('news_id', 0);
        $updated = Validator::parsedBody($request)->string('updated');
        $subject = Validator::parsedBody($request)->string('subject');
        $body    = Validator::parsedBody($request)->string('body');
        $brief   = Validator::parsedBody($request)->string('brief');
        $media_id   = Validator::parsedBody($request)->string('obje-xref');

        $subject = $this->html_service->sanitize($subject);
        $body    = $this->html_service->sanitize($body);
        $brief   = $this->html_service->sanitize($brief);

        if ($news_id !== 0) {
            DB::table('news')
                ->where('news_id', '=', $news_id)
                ->where('gedcom_id', '=', $tree->id())
                ->update([
                    'body'    => $body,
                    'subject' => $subject,
                    'brief' => $brief,
                    'media_id' => $media_id,
                    'updated' => $updated,
                ]);
        } else {
            if ($updated == null) {
                $updated = Carbon::now();
            }

            DB::table('news')->insert([
                'updated' => $updated,
                'body'      => $body,
                'subject'   => $subject,
                'brief' => $brief,
                'media_id' => $media_id,
                'gedcom_id' => $tree->id(),
            ]);
        }

        $url = route('module', [
            'tree' => $tree ? $tree->name() : null,
            'module' => $this->name(),
            'action' => 'Page',
        ]);

        return redirect($url);
    }

    public function postDeleteNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree    = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');

        if (!Auth::isManager($tree)) {
            throw new HttpAccessDeniedException();
        }

        DB::table('news_likes')
            ->where('news_id', '=', $news_id)
            ->delete();

        $comments = DB::table('news_comments')
            ->select('comments_id')
            ->where('news_id', '=', $news_id)
            ->get();

        foreach ($comments as $comment) {
            DB::table('comments_likes')
                ->where('comments_id', '=', $comment->comments_id)
                ->delete();
        }

        DB::table('news_comments')
            ->where('news_id', '=', $news_id)
            ->delete();

        DB::table('news')
            ->where('news_id', '=', $news_id)
            ->where('gedcom_id', '=', $tree->id())
            ->delete();

        $url = route('module', [
            'tree' => $tree ? $tree->name() : null,
            'module' => $this->name(),
            'action' => 'Page',
        ]);

        return redirect($url);
    }

    public function getShowNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id', 0);

        $individual1 = null;
        $like_exists = null;

        $row = DB::table('news')
            ->where('news_id', '=', $news_id)
            ->where('gedcom_id', '=', $tree->id())
            ->first();

        if ($row === null || (!auth::ismanager($tree) && (Registry::timestampFactory()->fromString($row->updated)->format('y-m-d h:i:s') >= date('y-m-d h:i:s')))) {
            throw new httpnotfoundexception(i18n::translate('%s does not exist.', 'news_id:' . $news_id));
        }

        $row->updated = Registry::timestampFactory()->fromString($row->updated);
        $media = Registry::mediaFactory()->make($row->media_id, $tree);

        $articles = db::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('updated', '<=', Carbon::now())
            ->orderbydesc('updated')
            ->limit(5)
            ->get()
            ->map(static function (object $row): object {
                $row->updated = registry::timestampfactory()->fromstring($row->updated);
                return $row;
            });

        $articles = $articles->map(function ($article) use ($tree) {
            $media_id = Registry::mediaFactory()->make($article->media_id, $tree);
            $article->media_id = $media_id;
            return $article;
        });

        $total_likes = DB::table('news_likes')
            ->where('news_id', $news_id)
            ->count();

        $total_comments = DB::table('news_comments')
            ->where('news_id', $news_id)
            ->count();

        $comments = DB::table('news_comments')
            ->join('user', 'news_comments.user_id', '=', 'user.user_id')
            ->where('news_id', $news_id)
            ->select('news_comments.*', 'user.real_name')
            ->limit(5)
            ->get()
            ->map(static function (object $row): object {
                $row->updated = Registry::timestampFactory()->fromString($row->updated);
                return $row;
            });

        $user_service = new UserService();

        $comments = $comments->map(function ($comment) use ($user_service, $tree) {
            $user_id = intval($comment->user_id);
            $user = $user_service->find($user_id);
            $gedcom_id = $tree->getUserPreference($user, 'gedcomid');
            $individual = Registry::individualFactory()->make($gedcom_id, $tree);
            $comment->individual = $individual;
            $likes_count = DB::table('comments_likes')
                ->where('comments_id', $comment->comments_id)
                ->count();
            $comment->likes_count = $likes_count;
            if (Auth::id() !== null) {
                $user_id = Auth::id();
                $like_exists = DB::table('comments_likes')
                    ->where('comments_id', $comment->comments_id)
                    ->where('user_id', $user_id)
                    ->exists();
                $comment->like_exists = $like_exists;
            }
            return $comment;
        });

        if (Auth::id() !== null) {

            $user_id = Auth::id();

            $like_exists = DB::table('news_likes')
                ->where('news_id', $news_id)
                ->where('user_id', $user_id)
                ->exists();

            $user_id = $user_service->find(Auth::id());
            $gedcom_id = $tree->getUserPreference($user_id, 'gedcomid');
            $individual1 = Registry::individualFactory()->make($gedcom_id, $tree);
        }

        $title = I18N::translate('News');

        return $this->viewResponse($this->name() . '::show', [
            'news_id' => $news_id,
            'subject' => $row->subject,
            'news_media'    => $media,
            'brief' => $row->brief,
            'body'    => $row->body,
            'updated' => $row->updated,
            'articles' => $articles,
            'individual1' => $individual1,
            'like_exists' => $like_exists,
            'total_likes' => $total_likes,
            'total_comments' => $total_comments,
            'comments' => $comments,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    public function getLikeNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $news_id = Validator::queryParams($request)->integer('news_id', 0);
        $user_id = Auth::id();

        $like_exists = DB::table('news_likes')
            ->where('news_id', $news_id)
            ->where('user_id', $user_id)
            ->exists();

        if (!$like_exists) {
            DB::table('news_likes')->insert([
                'news_id' => $news_id,
                'user_id' => $user_id,
            ]);
        }

        $total_likes = DB::table('news_likes')->where('news_id', $news_id)->count();

        return response([
            'success' => true,
            'data' => [
                'total_likes' => $total_likes,
            ],
        ]);
    }

    public function postShowNewsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user_id = Auth::id();

        $news_id = Validator::queryParams($request)->integer('news_id', 0);
        $comment = Validator::parsedBody($request)->string('comment');

        $comment = $this->html_service->sanitize($comment);


        DB::table('news_comments')->insert([
            'news_id'      => $news_id,
            'user_id'   => $user_id,
            'comment' => $comment,
        ]);

        $url = route('module', [
            'tree' => $tree ? $tree->name() : null,
            'module' => $this->name(),
            'action' => 'ShowNews',
            'news_id' => $news_id,
        ]);

        $message = I18N::translate('Comment added');
        FlashMessages::addMessage($message, 'success');

        return redirect($url);
    }
    public function postDeleteCommentsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree    = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');
        $comments_id = Validator::queryParams($request)->integer('comments_id');

        if (!Auth::isManager($tree)) {
            throw new HttpAccessDeniedException();
        }

        DB::table('news_comments')
            ->where('news_id', '=', $news_id)
            ->where('comments_id', '=', $comments_id)
            ->delete();

        DB::table('comments_likes')
            ->where('comments_id', '=', $comments_id)
            ->delete();

        $url = route('module', [
            'tree' => $tree ? $tree->name() : null,
            'module' => $this->name(),
            'action' => 'ShowNews',
            'news_id' => $news_id,
        ]);

        return redirect($url);
    }

    public function getLikeCommentsAction(ServerRequestInterface $request): ResponseInterface
    {
        $comments_id = Validator::queryParams($request)->integer('comments_id', 0);
        $user_id = Auth::id();

        $like_exists = DB::table('comments_likes')
            ->where('comments_id', $comments_id)
            ->where('user_id', $user_id)
            ->exists();

        if (!$like_exists) {
            DB::table('comments_likes')->insert([
                'comments_id' => $comments_id,
                'user_id' => $user_id,
            ]);
        }

        $likes_count = DB::table('comments_likes')->where('comments_id', $comments_id)->count();

        return response([
            'success' => true,
            'data' => [
                'likes_count' => $likes_count,
            ],
        ]);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'news_menu_order' => $this->getPreference('news_menu_order', '-1'),
            'limit_news' => $this->getPreference('limit_news', '5'),
            'limit_comments' => $this->getPreference('limit_comments', '5'),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        $this->setPreference('news_menu_order', $params['news_menu_order']);
        $this->setPreference('limit_news', $params['limit_news']);
        $this->setPreference('limit_comments', $params['limit_comments']);

        $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }
}
