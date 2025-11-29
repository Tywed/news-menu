<?php

namespace Tywed\Webtrees\Module\NewsMenu\Models;

class Category
{
    private int $categoryId;
    private string $name; // Fallback name from parent table
    private ?string $description;
    private int $sortOrder;

    /**
     * Translations cache: ['language' => 'name']
     * @var array<string, string>
     */
    private array $translations = [];

    /**
     * Category constructor
     *
     * @param int $categoryId
     * @param string $name Fallback name from parent table
     * @param ?string $description
     * @param int $sortOrder
     * @param array<string, string> $translations Optional translations cache
     */
    public function __construct(
        int $categoryId,
        string $name,
        ?string $description = null,
        int $sortOrder = 0,
        array $translations = []
    ) {
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->description = $description;
        $this->sortOrder = $sortOrder;
        $this->translations = $translations;
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
     * @param string|null $language Language code (e.g., 'en', 'de', 'en-GB')
     *                              If null, returns fallback name from parent table
     * @return string
     */
    public function getName(?string $language = null): string
    {
        // If no language specified, return fallback name
        if ($language === null) {
            return $this->name;
        }

        // Try exact language match first
        if (isset($this->translations[$language])) {
            return $this->translations[$language];
        }

        // Try fallback: if 'en-GB' requested but not found, try 'en'
        if (strlen($language) > 2) {
            $baseLanguage = substr($language, 0, 2);
            if (isset($this->translations[$baseLanguage])) {
                return $this->translations[$baseLanguage];
            }
        }

        // Try reverse: if 'en' requested but not found, try to find any 'en-*' variant
        if (strlen($language) === 2) {
            foreach ($this->translations as $lang => $name) {
                if (strlen($lang) > 2 && substr($lang, 0, 2) === $language) {
                    return $name;
                }
            }
        }

        // If no translation found, return fallback name
        return $this->name;
    }

    /**
     * Set translations cache
     *
     * @param array<string, string> $translations ['language' => 'name']
     * @return void
     */
    public function setTranslations(array $translations): void
    {
        $this->translations = $translations;
    }

    /**
     * Get all translations
     *
     * @return array<string, string> ['language' => 'name']
     */
    public function getTranslations(): array
    {
        return $this->translations;
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
