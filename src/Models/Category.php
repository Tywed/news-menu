<?php

namespace Tywed\Webtrees\Module\NewsMenu\Models;

class Category
{
    private int $categoryId;
    private string $name;
    private ?string $description;
    private int $sortOrder;

    /**
     * Category constructor
     *
     * @param int $categoryId
     * @param string $name
     * @param string|null $description
     * @param int $sortOrder
     */
    public function __construct(
        int $categoryId,
        string $name,
        ?string $description = null,
        int $sortOrder = 0
    ) {
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->description = $description;
        $this->sortOrder = $sortOrder;
    }

    /**
     * Get the category ID
     *
     * @return int
     */
    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    /**
     * Get the category name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the category description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
} 