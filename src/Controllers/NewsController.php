<?php

namespace Tywed\Webtrees\Module\NewsMenu\Controllers;

use Carbon\Carbon;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tywed\Webtrees\Module\NewsMenu\Services\NewsService;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CommentRepository;
use Fisharebest\Webtrees\View;
use Tywed\Webtrees\Module\NewsMenu\NewsMenu;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Registry;

class NewsController
{
    use ViewResponseTrait;
    
    private NewsService $newsService;
    private CommentRepository $commentRepository;
    private NewsMenu $module;
    private UserService $userService;

    public function __construct(
        NewsService $newsService,
        CommentRepository $commentRepository,
        NewsMenu $module
    ) {
        $this->newsService = $newsService;
        $this->commentRepository = $commentRepository;
        $this->module = $module;
        $this->userService = new UserService();
    }

    /**
     * Convert an array to a Collection if it isn't already one
     */
    private function ensureCollection($items): Collection
    {
        return $items instanceof Collection ? $items : new Collection($items);
    }

    public function page(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $currentPage = Validator::queryParams($request)->integer('page', 1);
        $limit = Validator::queryParams($request)->integer('limit', 5);
        $offset = ($currentPage - 1) * $limit;

        $totalArticles = $this->newsService->count($tree);
        $articles = $this->ensureCollection($this->newsService->findAll($tree, $limit, $offset));
        
        $currentLanguage = \Fisharebest\Webtrees\I18N::languageTag();
        $articles = $articles->filter(function($article) use ($currentLanguage) {
            $languages = $article->getLanguagesArray();
            return empty($languages) || in_array($currentLanguage, $languages);
        });
        
        // Get categories for the view
        $categories = $this->newsService->getAllCategories();
        
        // Try to get popular articles
        $minViews = (int)$this->module->getPreference('min_views_popular', '5');
        $popularArticles = $this->ensureCollection($this->newsService->findPopular($tree, 3, $minViews));
        
        $popularArticles = $popularArticles->filter(function($article) use ($currentLanguage) {
            $languages = $article->getLanguagesArray();
            return empty($languages) || in_array($currentLanguage, $languages);
        });

        return $this->viewResponse($this->module->name() . '::page-news', [
            'title' => I18N::translate('News'),
            'module_name' => $this->module->name(),
            'module' => $this->module,
            'tree' => $tree,
            'articles' => $articles,
            'limit' => $limit,
            'totalArticles' => $totalArticles,
            'current' => $currentPage,
            'categories' => $categories,
            'popular_articles' => $popularArticles,
        ]);
    }

    /**
     * Get individual for a user in the specified tree
     */
    private function getIndividualForUser(?int $user_id, Tree $tree)
    {
        if ($user_id === null) {
            return null;
        }
        
        $user = $this->userService->find($user_id);
        if ($user === null) {
            return null;
        }
        
        $gedcom_id = $tree->getUserPreference($user, 'gedcomid');
        return Registry::individualFactory()->make($gedcom_id, $tree);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');
        $user_id = Auth::id();

        $news = $this->newsService->find($news_id, $tree);
        if ($news === null) {
            throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
        }

        // Increment view count
        $this->newsService->incrementViewCount($news);

        // Get all data for the view
        $articles = $this->ensureCollection($this->newsService->findAll($tree, 5));
        $total_likes = $this->newsService->getLikesCount($news_id);
        $total_comments = $this->commentRepository->countByNews($news_id);
        
        $limit_comments = (int)$this->module->getPreference('limit_comments', '5');
        
        $comments = $this->commentRepository->findByNews($news_id, 999);
        
        $like_exists = $user_id !== null ? $this->newsService->hasUserLiked($news_id, $user_id) : false;
        $categories = $this->newsService->getAllCategories();
        
        // Try to get popular articles
        $minViews = (int)$this->module->getPreference('min_views_popular', '5');
        $popularArticles = $this->ensureCollection($this->newsService->findPopular($tree, 3, $minViews));
        
        // Process comments to add individual and like information
        foreach ($comments as $comment) {
            $individual = $this->getIndividualForUser($comment->getUserId(), $tree);
            $comment->setIndividual($individual);
            
            // Set like information
            $hasLiked = $user_id !== null && $this->commentRepository->hasUserLiked($comment->getCommentsId(), $user_id);
            $comment->setLikeExists($hasLiked);
            $comment->setLikesCount($this->commentRepository->getLikesCount($comment->getCommentsId()));
        }

        // Get Individual of current user for comment form
        $individual1 = $this->getIndividualForUser($user_id, $tree);
        
        // Get the author of the news (for now using a placeholder)
        $author = 'Administrator';
        
        // Фильтрация боковых блоков статей по текущему языку пользователя
        $currentLanguage = \Fisharebest\Webtrees\I18N::languageTag();
        $articles = $articles->filter(function($article) use ($currentLanguage, $news_id) {
            // Исключаем текущую новость
            if ($article->getNewsId() === $news_id) {
                return false;
            }
            $languages = $article->getLanguagesArray();
            // Если языки не указаны, показываем для всех
            return empty($languages) || in_array($currentLanguage, $languages);
        });
        
        // Также фильтруем популярные статьи по языку
        $popularArticles = $popularArticles->filter(function($article) use ($currentLanguage, $news_id) {
            // Исключаем текущую новость
            if ($article->getNewsId() === $news_id) {
                return false;
            }
            $languages = $article->getLanguagesArray();
            return empty($languages) || in_array($currentLanguage, $languages);
        });

        return $this->viewResponse($this->module->name() . '::show', [
            'module_name' => $this->module->name(),
            'module' => $this->module,
            'news_id' => $news_id,
            'subject' => $news->getSubject(),
            'news_media' => $news->getMedia($tree),
            'brief' => $news->getBrief(),
            'body' => $news->getBody(),
            'updated' => $news->getUpdated(),
            'articles' => $articles,
            'popular_articles' => $popularArticles,
            'like_exists' => $like_exists,
            'total_likes' => $total_likes,
            'total_comments' => $total_comments,
            'comments' => $comments,
            'individual1' => $individual1, // Pass Individual of current user
            'title' => I18N::translate('News'),
            'tree' => $tree,
            'category_id' => $news->getCategoryId(),
            'categories' => $categories,
            'is_pinned' => $news->isPinned(),
            'view_count' => $news->getViewCount(),
            'author' => $author,
            'date' => $news->getUpdated()->format('Y-m-d'),
            'limit_comments' => $limit_comments,
        ]);
    }

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        if (!$this->module->canEditNews($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news_id = Validator::queryParams($request)->integer('news_id', 0);
        $categories = $this->newsService->getAllCategories();

        if ($news_id !== 0) {
            $news = $this->newsService->find($news_id, $tree);

            if ($news === null) {
                throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
            }

            $media = $news->getMedia($tree);
            $languages = $news->getLanguagesArray();
        } else {
            $news = null;
            $media = null;
            $languages = [];
        }

        $title = I18N::translate('Add/edit a journal/news entry');

        return $this->viewResponse($this->module->name() . '::edit', [
            'news_id' => $news_id,
            'updated' => $news ? $news->getUpdated() : null,
            'subject' => $news ? $news->getSubject() : '',
            'brief' => $news ? $news->getBrief() : '',
            'body' => $news ? $news->getBody() : '',
            'media' => $media,
            'title' => $title,
            'tree' => $tree,
            'category_id' => $news ? $news->getCategoryId() : null,
            'categories' => $categories,
            'is_pinned' => $news ? $news->isPinned() : false,
            'languages' => $languages,
        ]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        if (!$this->module->canEditNews($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news_id = Validator::queryParams($request)->integer('news_id', 0);
        $updated = Validator::parsedBody($request)->string('updated');
        $subject = Validator::parsedBody($request)->string('subject');
        $body = Validator::parsedBody($request)->string('body');
        $brief = Validator::parsedBody($request)->string('brief');
        $media_id = Validator::parsedBody($request)->string('obje-xref');
        
        // Handle empty category value (empty string) properly
        $category_id_raw = $request->getParsedBody()['category_id'] ?? '';
        $category_id = $category_id_raw === '' ? null : (int)$category_id_raw;
        
        $is_pinned = (bool)Validator::parsedBody($request)->integer('is_pinned', 0);
        
        // Get selected languages
        $languages = Validator::parsedBody($request)->array('languages', []);
        $languages_str = implode(',', $languages);

        if ($news_id !== 0) {
            $news = $this->newsService->find($news_id, $tree);
            $this->newsService->update(
                $news, 
                $subject, 
                $brief, 
                $body, 
                $media_id, 
                Carbon::parse($updated),
                $category_id,
                $is_pinned,
                $languages_str
            );
        } else {
            $this->newsService->create(
                $tree, 
                $subject, 
                $brief, 
                $body, 
                $media_id, 
                Carbon::parse($updated),
                $category_id,
                $is_pinned,
                $languages_str
            );
        }

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->module->name(),
            'action' => 'Page',
        ]);

        return redirect($url);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');

        if (!$this->module->canEditNews($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news = $this->newsService->find($news_id, $tree);
        $this->newsService->delete($news);

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->module->name(),
            'action' => 'Page',
        ]);

        return redirect($url);
    }

    public function like(ServerRequestInterface $request): ResponseInterface
    {
        $news_id = Validator::queryParams($request)->integer('news_id');
        $user_id = Auth::id();

        $this->newsService->addLike($news_id, $user_id);

        $total_likes = $this->newsService->getLikesCount($news_id);

        return response([
            'success' => true,
            'data' => [
                'total_likes' => $total_likes,
            ],
        ]);
    }

    /**
     * Display news filtered by category
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function category(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $category_id = Validator::queryParams($request)->integer('category_id');
        $currentPage = Validator::queryParams($request)->integer('page', 1);
        $limit = Validator::queryParams($request)->integer('limit', 5);
        $offset = ($currentPage - 1) * $limit;

        $totalArticles = $this->newsService->countByCategory($tree, $category_id);
        $articles = $this->ensureCollection($this->newsService->findByCategory($tree, $category_id, $limit, $offset));
        
        // Фильтрация статей по текущему языку пользователя
        $currentLanguage = \Fisharebest\Webtrees\I18N::languageTag();
        $articles = $articles->filter(function($article) use ($currentLanguage) {
            $languages = $article->getLanguagesArray();
            // Если языки не указаны, показываем для всех
            return empty($languages) || in_array($currentLanguage, $languages);
        });
        
        $categories = $this->newsService->getAllCategories();
        
        // Find the current category
        $currentCategory = null;
        foreach ($categories as $category) {
            if ($category->getCategoryId() === $category_id) {
                $currentCategory = $category;
                break;
            }
        }
        
        if ($currentCategory === null) {
            throw new HttpNotFoundException(I18N::translate('Category not found'));
        }

        return $this->viewResponse($this->module->name() . '::page-news', [
            'title' => $currentCategory->getName(),
            'module_name' => $this->module->name(),
            'module' => $this->module,
            'tree' => $tree,
            'articles' => $articles,
            'limit' => $limit,
            'totalArticles' => $totalArticles,
            'current' => $currentPage,
            'categories' => $categories,
            'current_category' => $currentCategory,
        ]);
    }

    /**
     * Toggle pinned status for a news article
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function togglePinned(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');

        if (!$this->module->canEditNews($tree)) {
            throw new HttpAccessDeniedException();
        }

        $news = $this->newsService->find($news_id, $tree);
        
        if ($news === null) {
            throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
        }
        
        $isPinned = $this->newsService->togglePinned($news);
        
        $message = $isPinned 
            ? I18N::translate('News has been pinned')
            : I18N::translate('News has been unpinned');
            
        FlashMessages::addMessage($message, 'success');
        
        return redirect(route('module', [
            'module' => $this->module->name(),
            'action' => 'ShowNews',
            'news_id' => $news_id,
            'tree' => $tree->name(),
        ]));
    }
} 