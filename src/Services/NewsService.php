<?php

namespace Tywed\Webtrees\Module\NewsMenu\Services;

use Carbon\Carbon;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Services\HtmlService;
use Illuminate\Database\Capsule\Manager as DB;
use Tywed\Webtrees\Module\NewsMenu\Models\News;
use Tywed\Webtrees\Module\NewsMenu\Repositories\NewsRepository;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CommentRepository;
use Tywed\Webtrees\Module\NewsMenu\Repositories\CategoryRepository;
use Tywed\Webtrees\Module\NewsMenu\Helpers\AppHelper;

class NewsService
{
    private NewsRepository $newsRepository;
    private CommentRepository $commentRepository;
    private HtmlService $htmlService;
    private CategoryRepository $categoryRepository;

    public function __construct(
        NewsRepository $newsRepository,
        CommentRepository $commentRepository,
        HtmlService $htmlService,
        ?CategoryRepository $categoryRepository = null
    ) {
        $this->newsRepository = $newsRepository;
        $this->commentRepository = $commentRepository;
        $this->htmlService = $htmlService;
        $this->categoryRepository = $categoryRepository ?? new CategoryRepository();
    }

    public function find(int $news_id, Tree $tree): ?News
    {
        return $this->newsRepository->find($news_id, $tree);
    }

    public function findAll(Tree $tree, int $limit = 5, int $offset = 0): array
    {
        return $this->newsRepository->findAll($tree, $limit, $offset);
    }

    /**
     * Find popular news articles
     *
     * @param Tree $tree
     * @param int $limit
     * @param int $minViews
     * @return array
     */
    public function findPopular(Tree $tree, int $limit = 5, int $minViews = 5): array
    {
        return $this->newsRepository->findPopular($tree, $limit, $minViews);
    }

    /**
     * Find news by category
     *
     * @param Tree $tree
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findByCategory(Tree $tree, int $categoryId, int $limit = 5, int $offset = 0): array
    {
        return $this->newsRepository->findByCategory($tree, $categoryId, $limit, $offset);
    }

    public function count(Tree $tree): int
    {
        return $this->newsRepository->count($tree);
    }

    /**
     * Count news by category
     *
     * @param Tree $tree
     * @param int $categoryId
     * @return int
     */
    public function countByCategory(Tree $tree, int $categoryId): int
    {
        return $this->newsRepository->countByCategory($tree, $categoryId);
    }

    /**
     * Get all categories
     *
     * @return array
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    public function create(
        Tree $tree,
        string $subject,
        string $brief,
        string $body,
        ?string $media_id,
        ?Carbon $updated = null,
        ?int $categoryId = null,
        bool $isPinned = false,
        string $languages = ''
    ): News {
        $subject = $this->htmlService->sanitize($subject);
        $brief = $this->htmlService->sanitize($brief);
        $body = $this->htmlService->sanitize($body);

        return $this->newsRepository->create(
            $tree,
            $subject,
            $brief,
            $body,
            $media_id,
            $updated,
            $categoryId,
            $isPinned,
            $languages
        );
    }

    public function update(
        News $news,
        string $subject,
        string $brief,
        string $body,
        ?string $media_id,
        Carbon $updated,
        ?int $categoryId = null,
        bool $isPinned = false,
        string $languages = ''
    ): void {
        $subject = $this->htmlService->sanitize($subject);
        $brief = $this->htmlService->sanitize($brief);
        $body = $this->htmlService->sanitize($body);

        $this->newsRepository->update(
            $news,
            $subject,
            $brief,
            $body,
            $media_id,
            $updated,
            $categoryId,
            $isPinned,
            $languages
        );
    }

    /**
     * Increment the view count for a news article
     *
     * @param News $news
     * @return void
     */
    public function incrementViewCount(News $news): void
    {
        $this->newsRepository->incrementViewCount($news);
    }

    /**
     * Toggle the pinned status of a news article
     *
     * @param News $news
     * @return bool New pinned status
     */
    public function togglePinned(News $news): bool
    {
        return $this->newsRepository->togglePinned($news);
    }

    /**
     * Create a new category
     *
     * @param string $name
     * @param string|null $description
     * @param int $sortOrder
     * @return mixed
     */
    public function createCategory(string $name, ?string $description = null, int $sortOrder = 0)
    {
        $name = $this->htmlService->sanitize($name);
        if ($description !== null) {
            $description = $this->htmlService->sanitize($description);
        }

        return $this->categoryRepository->create($name, $description, $sortOrder);
    }

    /**
     * Update a category
     *
     * @param int $categoryId
     * @param string $name
     * @param string|null $description
     * @param int $sortOrder
     * @return void
     */
    public function updateCategory(int $categoryId, string $name, ?string $description = null, int $sortOrder = 0): void
    {
        $category = $this->categoryRepository->find($categoryId);
        if ($category !== null) {
            $name = $this->htmlService->sanitize($name);
            if ($description !== null) {
                $description = $this->htmlService->sanitize($description);
            }

            $this->categoryRepository->update($category, $name, $description, $sortOrder);
        }
    }

    /**
     * Delete a category
     *
     * @param int $categoryId
     * @return void
     */
    public function deleteCategory(int $categoryId): void
    {
        $category = $this->categoryRepository->find($categoryId);
        if ($category !== null) {
            $this->categoryRepository->delete($category);
        }
    }

    public function delete(News $news): void
    {
        $this->newsRepository->delete($news);
    }

    public function getLikesCount(int $news_id): int
    {
        return DB::table('news_likes')
            ->where('news_id', '=', $news_id)
            ->count();
    }

    public function hasUserLiked(int $news_id, int $user_id): bool
    {
        return DB::table('news_likes')
            ->where('news_id', '=', $news_id)
            ->where('user_id', '=', $user_id)
            ->exists();
    }

    public function addLike(int $news_id, int $user_id): void
    {
        if (!$this->hasUserLiked($news_id, $user_id)) {
            DB::table('news_likes')->insert([
                'news_id' => $news_id,
                'user_id' => $user_id,
            ]);
        }
    }

    public function canEdit(News $news, Tree $tree): bool
    {
        return Auth::isManager($tree);
    }

    public function canDelete(News $news, Tree $tree): bool
    {
        return Auth::isManager($tree);
    }
}
