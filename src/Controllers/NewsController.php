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
use Tywed\Webtrees\Module\NewsMenu\Helpers\AppHelper;
use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CategoryRepository;

class NewsController
{
    use ViewResponseTrait;
    
    private NewsService $newsService;
    private CommentRepository $commentRepository;
    private NewsMenu $module;
    private UserService $userService;
    private CategoryRepository $categoryRepository;

    public function __construct(
        NewsService $newsService,
        CommentRepository $commentRepository,
        NewsMenu $module,
        ?UserService $userService = null,
        ?CategoryRepository $categoryRepository = null
    ) {
        $this->newsService = $newsService;
        $this->commentRepository = $commentRepository;
        $this->module = $module;
        $this->userService = $userService ?? AppHelper::get(UserService::class);
        $this->categoryRepository = $categoryRepository ?? new CategoryRepository();
    }

    /**
     * Convert an array to a Collection if it isn't already one
     */
    private function ensureCollection($items): Collection
    {
        return $items instanceof Collection ? $items : new Collection($items);
    }

    /**
     * Filter articles by current language with optional exclusion
     * 
     * @param Collection $articles
     * @param int|null $excludeNewsId Optional news ID to exclude from results
     * @return Collection
     */
    private function filterByLanguage(Collection $articles, ?int $excludeNewsId = null): Collection
    {
        $currentLanguage = I18N::languageTag();
        
        return $articles->filter(function($article) use ($currentLanguage, $excludeNewsId) {
            // Exclude specific news if provided
            if ($excludeNewsId !== null && $article->getNewsId() === $excludeNewsId) {
                return false;
            }
            
            $languages = $article->getLanguagesArray();
            // If no languages specified, show for all languages
            return empty($languages) || in_array($currentLanguage, $languages);
        });
    }

    public function page(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $currentPage = Validator::queryParams($request)->integer('page', 1);
        $limit = Validator::queryParams($request)->integer('limit', 5);
        $offset = ($currentPage - 1) * $limit;

        $totalArticles = $this->newsService->count($tree);
        $articles = $this->ensureCollection($this->newsService->findAll($tree, $limit, $offset));
        
        // Filter articles by current language
        $articles = $this->filterByLanguage($articles);
        
        // Get categories for the view
        $categories = $this->newsService->getAllCategories();
        
        // Try to get popular articles
        $minViews = (int)$this->module->getPreference('min_views_popular', '5');
        $popularArticles = $this->ensureCollection($this->newsService->findPopular($tree, 3, $minViews));
        
        // Filter popular articles by current language
        $popularArticles = $this->filterByLanguage($popularArticles);

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
        // Use reasonable limit instead of 999
        $maxCommentsLimit = 100;
        $comments = $this->commentRepository->findByNews($news_id, $maxCommentsLimit);
        
        $like_exists = $user_id !== null ? $this->newsService->hasUserLiked($news_id, $user_id) : false;
        $categories = $this->newsService->getAllCategories();
        
        // Try to get popular articles
        $minViews = (int)$this->module->getPreference('min_views_popular', '5');
        $popularArticles = $this->ensureCollection($this->newsService->findPopular($tree, 3, $minViews));
        
        // Optimize N+1 queries: batch load likes and user data
        $commentsIds = array_map(fn($c) => $c->getCommentsId(), $comments);
        $likesCounts = $this->commentRepository->getLikesCountBatch($commentsIds);
        $userLikedComments = $user_id !== null 
            ? $this->commentRepository->getUserLikedComments($commentsIds, $user_id) 
            : [];
        
        // Get unique user IDs for batch loading
        $userIds = array_unique(array_map(fn($c) => $c->getUserId(), $comments));
        $users = [];
        foreach ($userIds as $uid) {
            $users[$uid] = $this->userService->find($uid);
        }
        
        // Process comments to add individual and like information
        foreach ($comments as $comment) {
            $userId = $comment->getUserId();
            $user = $users[$userId] ?? null;
            if ($user !== null) {
                $gedcom_id = $tree->getUserPreference($user, 'gedcomid');
                $individual = Registry::individualFactory()->make($gedcom_id, $tree);
                $comment->setIndividual($individual);
            }
            
            // Set like information from batch data
            $commentId = $comment->getCommentsId();
            $comment->setLikesCount($likesCounts[$commentId] ?? 0);
            $comment->setLikeExists(in_array($commentId, $userLikedComments));
        }

        // Get Individual of current user for comment form (optimized)
        $individual1 = null;
        if ($user_id !== null) {
            $user = $this->userService->find($user_id);
            if ($user !== null) {
                $gedcom_id = $tree->getUserPreference($user, 'gedcomid');
                $individual1 = Registry::individualFactory()->make($gedcom_id, $tree);
            }
        }
        
        // Get the author of the news (for now using a placeholder)
        $author = 'Administrator';
        
        // Filter articles by current language, excluding current news
        $articles = $this->filterByLanguage($articles, $news_id);
        
        // Filter popular articles by current language, excluding current news
        $popularArticles = $this->filterByLanguage($popularArticles, $news_id);

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
        
        // Validate input data
        if (mb_strlen(trim($subject)) === 0) {
            FlashMessages::addMessage(I18N::translate('Subject cannot be empty'), 'danger');
            return redirect(route('module', [
                'tree' => $tree->name(),
                'module' => $this->module->name(),
                'action' => 'EditNews',
                'news_id' => $news_id,
            ]));
        }
        
        if (mb_strlen($subject) > 255) {
            FlashMessages::addMessage(I18N::translate('Subject is too long (maximum 255 characters)'), 'danger');
            return redirect(route('module', [
                'tree' => $tree->name(),
                'module' => $this->module->name(),
                'action' => 'EditNews',
                'news_id' => $news_id,
            ]));
        }
        
        // Validate date
        try {
            $updatedDate = Carbon::parse($updated);
        } catch (\Exception $e) {
            FlashMessages::addMessage(I18N::translate('Invalid date format'), 'danger');
            return redirect(route('module', [
                'tree' => $tree->name(),
                'module' => $this->module->name(),
                'action' => 'EditNews',
                'news_id' => $news_id,
            ]));
        }
        
        // Validate media_id if provided
        // Note: media_id is stored as string with default '' (not nullable in DB)
        if ($media_id !== '' && $media_id !== null) {
            $media = Registry::mediaFactory()->make($media_id, $tree);
            if ($media === null) {
                FlashMessages::addMessage(I18N::translate('Media object not found'), 'danger');
                return redirect(route('module', [
                    'tree' => $tree->name(),
                    'module' => $this->module->name(),
                    'action' => 'EditNews',
                    'news_id' => $news_id,
                ]));
            }
        } else {
            // Use empty string instead of null, as DB field is not nullable
            $media_id = '';
        }
        
        // Handle empty category value (empty string) properly
        $category_id_raw = $request->getParsedBody()['category_id'] ?? '';
        $category_id = $category_id_raw === '' ? null : (int)$category_id_raw;
        
        // Validate category_id if provided
        if ($category_id !== null) {
            $category = $this->categoryRepository->find($category_id);
            if ($category === null) {
                FlashMessages::addMessage(I18N::translate('Category not found'), 'danger');
                return redirect(route('module', [
                    'tree' => $tree->name(),
                    'module' => $this->module->name(),
                    'action' => 'EditNews',
                    'news_id' => $news_id,
                ]));
            }
        }
        
        $is_pinned = (bool)Validator::parsedBody($request)->integer('is_pinned', 0);
        
        // Get selected languages
        $languages = Validator::parsedBody($request)->array('languages', []);
        $languages_str = implode(',', $languages);

        if ($news_id !== 0) {
            $news = $this->newsService->find($news_id, $tree);
            if ($news === null) {
                throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
            }
            $this->newsService->update(
                $news, 
                $subject, 
                $brief, 
                $body, 
                $media_id, 
                $updatedDate,
                $category_id,
                $is_pinned,
                $languages_str
            );
            FlashMessages::addMessage(I18N::translate('News updated successfully'), 'success');
        } else {
            $this->newsService->create(
                $tree, 
                $subject, 
                $brief, 
                $body, 
                $media_id, 
                $updatedDate,
                $category_id,
                $is_pinned,
                $languages_str
            );
            FlashMessages::addMessage(I18N::translate('News created successfully'), 'success');
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
        if ($news === null) {
            throw new HttpNotFoundException(I18N::translate('%s does not exist.', 'news_id:' . $news_id));
        }
        
        $this->newsService->delete($news);
        FlashMessages::addMessage(I18N::translate('News deleted successfully'), 'success');

        $url = route('module', [
            'tree' => $tree->name(),
            'module' => $this->module->name(),
            'action' => 'Page',
        ]);

        return redirect($url);
    }

    public function like(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $news_id = Validator::queryParams($request)->integer('news_id');
        $user_id = Auth::id();
        
        if ($user_id === null) {
            return response([
                'success' => false,
                'message' => I18N::translate('You must be logged in to like news'),
            ], 401);
        }

        // Validate news exists
        $news = $this->newsService->find($news_id, $tree);
        if ($news === null) {
            return response([
                'success' => false,
                'message' => I18N::translate('News not found'),
            ], 404);
        }

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
        
        // Filter articles by current language
        $articles = $this->filterByLanguage($articles);
        
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
            'title' => $currentCategory->getName(I18N::languageTag()),
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