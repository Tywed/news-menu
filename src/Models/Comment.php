<?php

namespace Tywed\Webtrees\Module\NewsMenu\Models;

use Carbon\Carbon;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;

class Comment
{
    private int $comments_id;
    private int $news_id;
    private int $user_id;
    private string $comment;
    private Carbon $updated;
    private ?Individual $individual = null;
    private int $likes_count = 0;
    private bool $like_exists = false;
    private string $real_name = '';

    public function __construct(
        int $comments_id,
        int $news_id,
        int $user_id,
        string $comment,
        Carbon $updated
    ) {
        $this->comments_id = $comments_id;
        $this->news_id = $news_id;
        $this->user_id = $user_id;
        $this->comment = $comment;
        $this->updated = $updated;
    }

    public function getCommentsId(): int
    {
        return $this->comments_id;
    }

    public function getNewsId(): int
    {
        return $this->news_id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getUpdated(): Carbon
    {
        return $this->updated;
    }

    public function getIndividual(): ?Individual
    {
        return $this->individual;
    }

    public function setIndividual(?Individual $individual): void
    {
        $this->individual = $individual;
    }

    public function getLikesCount(): int
    {
        return $this->likes_count;
    }

    public function setLikesCount(int $likes_count): void
    {
        $this->likes_count = $likes_count;
    }

    public function isLikeExists(): bool
    {
        return $this->like_exists;
    }

    public function setLikeExists(bool $like_exists): void
    {
        $this->like_exists = $like_exists;
    }

    public function getRealName(): string
    {
        return $this->real_name;
    }

    public function setRealName(string $real_name): void
    {
        $this->real_name = $real_name;
    }
} 