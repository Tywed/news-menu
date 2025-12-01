<?php

namespace Tywed\Webtrees\Module\NewsMenu\Repositories;

use Carbon\Carbon;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Tywed\Webtrees\Module\NewsMenu\Models\News;

class NewsRepository
{
    public function find(int $news_id, Tree $tree): ?News
    {
        $row = DB::table('news')
            ->where('news_id', '=', $news_id)
            ->where('gedcom_id', '=', $tree->id())
            ->first();

        if ($row === null) {
            return null;
        }

        return new News(
            $row->news_id,
            $row->gedcom_id,
            (int)($row->user_id ?? 0),
            $row->subject,
            $row->brief,
            $row->body,
            $row->media_id,
            Carbon::parse($row->updated),
            $row->category_id ?? null,
            $row->languages ?? '',
            (bool)($row->is_pinned ?? false),
            (int)($row->view_count ?? 0)
        );
    }

    public function findAll(Tree $tree, int $limit = 5, int $offset = 0): array
    {
        $rows = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->orderBy('is_pinned', 'desc')
            ->orderByDesc('updated')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $news = [];
        foreach ($rows as $row) {
            $news[] = new News(
                $row->news_id,
                $row->gedcom_id,
                (int)($row->user_id ?? 0),
                $row->subject,
                $row->brief,
                $row->body,
                $row->media_id,
                Carbon::parse($row->updated),
                $row->category_id ?? null,
                $row->languages ?? '',
                (bool)($row->is_pinned ?? false),
                (int)($row->view_count ?? 0)
            );
        }

        return $news;
    }

    /**
     * Find popular news articles sorted by popularity score
     *
     * @param Tree $tree
     * @param int $limit
     * @param int $minViews Minimum view count to be considered popular
     * @return array
     */
    public function findPopular(Tree $tree, int $limit = 5, int $minViews = 5): array
    {
        // First do a simple query to check if there's enough data
        $newsCount = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('view_count', '>=', $minViews)
            ->count();

        if ($newsCount === 0) {
            return [];
        }

        try {
            $query = DB::table('news')
                ->select('news.*')
                ->where('news.gedcom_id', '=', $tree->id())
                ->where('news.view_count', '>=', $minViews);

            // Get like counts
            $likeSubquery = DB::table('news_likes')
                ->select('news_id', DB::raw('COUNT(DISTINCT user_id) as likes_count'))
                ->groupBy('news_id');

            // Get comment counts
            $commentSubquery = DB::table('news_comments')
                ->select('news_id', DB::raw('COUNT(DISTINCT comments_id) as comments_count'))
                ->groupBy('news_id');

            $rows = $query
                ->leftJoinSub($likeSubquery, 'like_counts', 'news.news_id', '=', 'like_counts.news_id')
                ->leftJoinSub($commentSubquery, 'comment_counts', 'news.news_id', '=', 'comment_counts.news_id')
                ->select(
                    'news.*',
                    DB::raw('COALESCE(like_counts.likes_count, 0) as likes_count'),
                    DB::raw('COALESCE(comment_counts.comments_count, 0) as comments_count')
                )
                ->orderByRaw('(news.view_count + COALESCE(like_counts.likes_count, 0)*2 + COALESCE(comment_counts.comments_count, 0)*3) DESC')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            // Fallback to a simpler query if the complex one fails
            $rows = DB::table('news')
                ->where('gedcom_id', '=', $tree->id())
                ->where('view_count', '>=', $minViews)
                ->orderByDesc('view_count')
                ->limit($limit)
                ->get();
        }

        $news = [];
        foreach ($rows as $row) {
            $news[] = new News(
                $row->news_id,
                $row->gedcom_id,
                (int)($row->user_id ?? 0),
                $row->subject,
                $row->brief,
                $row->body,
                $row->media_id,
                Carbon::parse($row->updated),
                $row->category_id ?? null,
                $row->languages ?? '',
                (bool)($row->is_pinned ?? false),
                (int)($row->view_count ?? 0)
            );
        }

        return $news;
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
        $rows = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('category_id', '=', $categoryId)
            ->orderBy('is_pinned', 'desc')
            ->orderByDesc('updated')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $news = [];
        foreach ($rows as $row) {
            $news[] = new News(
                $row->news_id,
                $row->gedcom_id,
                (int)($row->user_id ?? 0),
                $row->subject,
                $row->brief,
                $row->body,
                $row->media_id,
                Carbon::parse($row->updated),
                $row->category_id ?? null,
                $row->languages ?? '',
                (bool)($row->is_pinned ?? false),
                (int)($row->view_count ?? 0)
            );
        }

        return $news;
    }

    public function count(Tree $tree): int
    {
        return DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->count();
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
        return DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('category_id', '=', $categoryId)
            ->count();
    }

    /**
     * Find news by author (user_id)
     * 
     * @param Tree $tree
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findByAuthor(Tree $tree, int $userId, int $limit = 5, int $offset = 0): array
    {
        $rows = DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $userId)
            ->orderBy('is_pinned', 'desc')
            ->orderByDesc('updated')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $news = [];
        foreach ($rows as $row) {
            $news[] = new News(
                $row->news_id,
                $row->gedcom_id,
                (int)($row->user_id ?? 0),
                $row->subject,
                $row->brief,
                $row->body,
                $row->media_id,
                Carbon::parse($row->updated),
                $row->category_id ?? null,
                $row->languages ?? '',
                (bool)($row->is_pinned ?? false),
                (int)($row->view_count ?? 0)
            );
        }

        return $news;
    }

    /**
     * Count news by author
     * 
     * @param Tree $tree
     * @param int $userId
     * @return int
     */
    public function countByAuthor(Tree $tree, int $userId): int
    {
        return DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $userId)
            ->count();
    }

    public function create(
        Tree $tree,
        int $user_id,
        string $subject, 
        string $brief, 
        string $body, 
        ?string $media_id, 
        ?Carbon $updated = null,
        ?int $categoryId = null,
        bool $isPinned = false,
        string $languages = ''
    ): News {
        $updated = $updated ?? Carbon::now();

        // Ensure media_id is empty string instead of null (DB field is not nullable)
        $media_id = $media_id ?? '';

        // Create a new News record
        $news_id = DB::table('news')->insertGetId([
            'gedcom_id' => $tree->id(),
            'user_id' => $user_id,
            'subject' => $subject,
            'brief' => $brief,
            'body' => $body,
            'media_id' => $media_id,
            'updated' => $updated,
            'category_id' => $categoryId,
            'languages' => $languages,
            'is_pinned' => $isPinned,
            'view_count' => 0,
        ]);

        return new News(
            $news_id,
            $tree->id(),
            $user_id,
            $subject,
            $brief,
            $body,
            $media_id,
            $updated,
            $categoryId,
            $languages,
            $isPinned,
            0
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
        string $languages = '',
        ?int $userId = null
    ): void {
        // Ensure media_id is empty string instead of null (DB field is not nullable)
        $media_id = $media_id ?? '';
        
        $updateData = [
            'subject' => $subject,
            'brief' => $brief,
            'body' => $body,
            'media_id' => $media_id,
            'updated' => $updated,
            'category_id' => $categoryId,
            'languages' => $languages,
            'is_pinned' => $isPinned,
        ];
        
        // Update user_id only if provided (for old news without author)
        if ($userId !== null) {
            $updateData['user_id'] = $userId;
        }
        
        DB::table('news')
            ->where('news_id', '=', $news->getNewsId())
            ->where('gedcom_id', '=', $news->getGedcomId())
            ->update($updateData);
    }

    /**
     * Update the view count for a news article
     *
     * @param News $news
     * @return void
     */
    public function incrementViewCount(News $news): void
    {
        DB::table('news')
            ->where('news_id', '=', $news->getNewsId())
            ->increment('view_count');
    }

    /**
     * Toggle pinned status for a news article
     *
     * @param News $news
     * @return bool New pinned status
     */
    public function togglePinned(News $news): bool
    {
        $newStatus = !$news->isPinned();

        // If we're pinning this news, unpin all others first
        if ($newStatus) {
            DB::table('news')
                ->where('news_id', '!=', $news->getNewsId())
                ->update([
                    'is_pinned' => false,
                ]);
        }

        DB::table('news')
            ->where('news_id', '=', $news->getNewsId())
            ->update([
                'is_pinned' => $newStatus,
            ]);

        return $newStatus;
    }

    public function delete(News $news): void
    {
        DB::table('news')
            ->where('news_id', '=', $news->getNewsId())
            ->where('gedcom_id', '=', $news->getGedcomId())
            ->delete();
    }
}
