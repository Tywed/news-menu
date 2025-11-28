<?php

namespace Tywed\Webtrees\Module\NewsMenu\Repositories;

use Carbon\Carbon;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Tywed\Webtrees\Module\NewsMenu\Models\Comment;

class CommentRepository
{
    public function find(int $comments_id): ?Comment
    {
        $row = DB::table('news_comments')
            ->join('user', 'news_comments.user_id', '=', 'user.user_id')
            ->where('comments_id', '=', $comments_id)
            ->select('news_comments.*', 'user.real_name')
            ->first();

        if ($row === null) {
            return null;
        }

        $comment = new Comment(
            $row->comments_id,
            $row->news_id,
            $row->user_id,
            $row->comment,
            Carbon::parse($row->updated)
        );
        
        $comment->setRealName($row->real_name);
        
        return $comment;
    }

    public function findByNews(int $news_id, int $limit = 5): array
    {
        $rows = DB::table('news_comments')
            ->join('user', 'news_comments.user_id', '=', 'user.user_id')
            ->where('news_id', '=', $news_id)
            ->select('news_comments.*', 'user.real_name')
            ->orderByDesc('news_comments.updated')
            ->limit($limit)
            ->get();

        $comments = [];
        foreach ($rows as $row) {
            $comment = new Comment(
                $row->comments_id,
                $row->news_id,
                $row->user_id,
                $row->comment,
                Carbon::parse($row->updated)
            );
            $comment->setRealName($row->real_name);
            $comments[] = $comment;
        }

        return $comments;
    }

    public function countByNews(int $news_id): int
    {
        return DB::table('news_comments')
            ->where('news_id', '=', $news_id)
            ->count();
    }

    public function create(int $news_id, int $user_id, string $comment): Comment
    {
        $comments_id = DB::table('news_comments')->insertGetId([
            'news_id' => $news_id,
            'user_id' => $user_id,
            'comment' => $comment,
            'updated' => Carbon::now(),
        ]);
        
        $real_name = DB::table('user')
            ->where('user_id', '=', $user_id)
            ->value('real_name');

        $newComment = new Comment(
            $comments_id,
            $news_id,
            $user_id,
            $comment,
            Carbon::now()
        );
        
        $newComment->setRealName($real_name ?? '');
        
        return $newComment;
    }

    public function delete(Comment $comment): void
    {
        DB::table('news_comments')
            ->where('comments_id', '=', $comment->getCommentsId())
            ->delete();
    }

    public function getLikesCount(int $comments_id): int
    {
        return DB::table('comments_likes')
            ->where('comments_id', '=', $comments_id)
            ->count();
    }

    public function hasUserLiked(int $comments_id, int $user_id): bool
    {
        return DB::table('comments_likes')
            ->where('comments_id', '=', $comments_id)
            ->where('user_id', '=', $user_id)
            ->exists();
    }

    public function addLike(int $comments_id, int $user_id): void
    {
        if (!$this->hasUserLiked($comments_id, $user_id)) {
            DB::table('comments_likes')->insert([
                'comments_id' => $comments_id,
                'user_id' => $user_id,
            ]);
        }
    }

    /**
     * Get likes count for multiple comments at once
     * 
     * @param array $commentsIds
     * @return array Associative array [comment_id => likes_count]
     */
    public function getLikesCountBatch(array $commentsIds): array
    {
        if (empty($commentsIds)) {
            return [];
        }

        $rows = DB::table('comments_likes')
            ->select('comments_id', DB::raw('COUNT(*) as likes_count'))
            ->whereIn('comments_id', $commentsIds)
            ->groupBy('comments_id')
            ->get();

        $result = [];
        foreach ($commentsIds as $id) {
            $result[$id] = 0;
        }

        foreach ($rows as $row) {
            $result[$row->comments_id] = (int)$row->likes_count;
        }

        return $result;
    }

    /**
     * Check which comments user has liked
     * 
     * @param array $commentsIds
     * @param int $user_id
     * @return array Array of comment IDs that user has liked
     */
    public function getUserLikedComments(array $commentsIds, int $user_id): array
    {
        if (empty($commentsIds)) {
            return [];
        }

        return DB::table('comments_likes')
            ->whereIn('comments_id', $commentsIds)
            ->where('user_id', '=', $user_id)
            ->pluck('comments_id')
            ->toArray();
    }
} 