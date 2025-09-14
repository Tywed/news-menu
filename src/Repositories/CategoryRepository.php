<?php

namespace Tywed\Webtrees\Module\NewsMenu\Repositories;

use Fisharebest\Webtrees\Registry;
use Illuminate\Database\Capsule\Manager as DB;
use Tywed\Webtrees\Module\NewsMenu\Models\Category;

class CategoryRepository
{
    /**
     * Find a category by ID
     *
     * @param int $categoryId
     * @return Category|null
     */
    public function find(int $categoryId): ?Category
    {
        $row = DB::table('news_categories')
            ->where('category_id', '=', $categoryId)
            ->first();

        if ($row === null) {
            return null;
        }

        return new Category(
            $row->category_id,
            $row->name,
            $row->description,
            $row->sort_order
        );
    }

    /**
     * Find all categories
     *
     * @return array
     */
    public function findAll(): array
    {
        $rows = DB::table('news_categories')
            ->orderBy('sort_order')
            ->get();

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = new Category(
                $row->category_id,
                $row->name,
                $row->description,
                $row->sort_order
            );
        }

        return $categories;
    }

    /**
     * Get all translations for a category as language => name
     */
    public function getTranslations(int $categoryId): array
    {
        if (!DB::schema()->hasTable('news_category_translations')) {
            return [];
        }

        $rows = DB::table('news_category_translations')
            ->where('category_id', '=', $categoryId)
            ->get();

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row->language] = $row->name;
        }

        return $translations;
    }

    /**
     * Get list of language codes with existing translation
     */
    public function getTranslationLanguages(int $categoryId): array
    {
        return array_keys($this->getTranslations($categoryId));
    }

    /**
     * Create or update a translation
     */
    public function upsertTranslation(int $categoryId, string $language, string $name): void
    {
        if (!DB::schema()->hasTable('news_category_translations')) {
            return;
        }

        DB::table('news_category_translations')->updateOrInsert(
            ['category_id' => $categoryId, 'language' => $language],
            ['name' => $name]
        );
    }

    /**
     * Delete a translation
     */
    public function deleteTranslation(int $categoryId, string $language): void
    {
        if (!DB::schema()->hasTable('news_category_translations')) {
            return;
        }

        DB::table('news_category_translations')
            ->where('category_id', '=', $categoryId)
            ->where('language', '=', $language)
            ->delete();
    }

    /**
     * Resolve translated name by language with fallbacks
     */
    public function resolveName(Category $category, string $language): string
    {
        if (!DB::schema()->hasTable('news_category_translations')) {
            return $category->getName();
        }

        $exact = DB::table('news_category_translations')
            ->where('category_id', '=', $category->getCategoryId())
            ->where('language', '=', $language)
            ->value('name');
        if ($exact) {
            return $exact;
        }

        if (strpos($language, '-') !== false) {
            $base = substr($language, 0, strpos($language, '-'));
            $baseName = DB::table('news_category_translations')
                ->where('category_id', '=', $category->getCategoryId())
                ->where('language', '=', $base)
                ->value('name');
            if ($baseName) {
                return $baseName;
            }
        }

        return $category->getName();
    }

    /**
     * Create a new category
     *
     * @param string $name
     * @param string|null $description
     * @param int $sortOrder
     * @return Category
     */
    public function create(string $name, ?string $description = null, int $sortOrder = 0): Category
    {
        $categoryId = DB::table('news_categories')->insertGetId([
            'name' => $name,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        return new Category(
            $categoryId,
            $name,
            $description,
            $sortOrder
        );
    }

    /**
     * Update a category
     *
     * @param Category $category
     * @param string $name
     * @param string|null $description
     * @param int $sortOrder
     * @return void
     */
    public function update(Category $category, string $name, ?string $description = null, int $sortOrder = 0): void
    {
        DB::table('news_categories')
            ->where('category_id', '=', $category->getCategoryId())
            ->update([
                'name' => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
            ]);
    }

    /**
     * Delete a category
     *
     * @param Category $category
     * @return void
     */
    public function delete(Category $category): void
    {
        DB::table('news_categories')
            ->where('category_id', '=', $category->getCategoryId())
            ->delete();
    }
} 