<?php

namespace Tywed\Webtrees\Module\NewsMenu\Models;

use Carbon\Carbon;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Registry;

class News
{
    private int $news_id;
    private int $gedcom_id;
    private string $subject;
    private string $brief;
    private string $body;
    private ?string $media_id;
    private Carbon $updated;
    private ?int $category_id;
    private string $languages;
    private bool $is_pinned;
    private int $view_count;

    public function __construct(
        int $news_id,
        int $gedcom_id,
        string $subject,
        string $brief,
        string $body,
        ?string $media_id,
        Carbon $updated,
        ?int $category_id = null,
        string $languages = '',
        bool $is_pinned = false,
        int $view_count = 0
    ) {
        $this->news_id = $news_id;
        $this->gedcom_id = $gedcom_id;
        $this->subject = $subject;
        $this->brief = $brief;
        $this->body = $body;
        $this->media_id = $media_id;
        $this->updated = $updated;
        $this->category_id = $category_id;
        $this->languages = $languages;
        $this->is_pinned = $is_pinned;
        $this->view_count = $view_count;
    }

    public function getNewsId(): int
    {
        return $this->news_id;
    }

    public function getGedcomId(): int
    {
        return $this->gedcom_id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBrief(): string
    {
        return $this->brief;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMediaId(): ?string
    {
        return $this->media_id;
    }

    public function getUpdated(): Carbon
    {
        return $this->updated;
    }

    public function getCategoryId(): ?int
    {
        return $this->category_id;
    }

    public function getLanguages(): string
    {
        return $this->languages;
    }

    public function getLanguagesArray(): array
    {
        return $this->languages ? explode(',', $this->languages) : [];
    }

    public function isPinned(): bool
    {
        return $this->is_pinned;
    }

    public function getViewCount(): int
    {
        return $this->view_count;
    }

    public function getMedia(Tree $tree): ?Media
    {
        if ($this->media_id === null) {
            return null;
        }

        return Registry::mediaFactory()->make($this->media_id, $tree);
    }
} 