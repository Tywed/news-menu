<?php

namespace Tywed\Webtrees\Module\NewsMenu\Repositories;

use Fisharebest\Webtrees\Registry;
use Illuminate\Database\Capsule\Manager as DB;
use Tywed\Webtrees\Module\NewsMenu\Models\Category;

class CategoryRepository
{
    /**
     * Find a category by ID with translations
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

        // Load translations
        $translations = $this->getTranslationsForCategory($categoryId);

        return new Category(
            $row->category_id,
            $row->name,
            $row->description,
            $row->sort_order,
            $translations
        );
    }

    /**
     * Get translations for a category
     *
     * @param int $categoryId
     * @return array<string, string> ['language' => 'name']
     */
    private function getTranslationsForCategory(int $categoryId): array
    {
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
     * Get translations for multiple categories (batch loading)
     *
     * @param array<int> $categoryIds
     * @return array<int, array<string, string>> [category_id => ['language' => 'name']]
     */
    private function getTranslationsForCategories(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $rows = DB::table('news_category_translations')
            ->whereIn('category_id', $categoryIds)
            ->get();

        $translations = [];
        foreach ($categoryIds as $id) {
            $translations[$id] = [];
        }

        foreach ($rows as $row) {
            $translations[$row->category_id][$row->language] = $row->name;
        }

        return $translations;
    }

    /**
     * Find all categories with translations (optimized batch loading)
     *
     * @return array<Category>
     */
    public function findAll(): array
    {
        $rows = DB::table('news_categories')
            ->orderBy('sort_order')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Batch load all translations
        $categoryIds = $rows->pluck('category_id')->toArray();
        $allTranslations = $this->getTranslationsForCategories($categoryIds);

        $categories = [];
        foreach ($rows as $row) {
            $translations = $allTranslations[$row->category_id] ?? [];

            $categories[] = new Category(
                $row->category_id,
                $row->name,
                $row->description,
                $row->sort_order,
                $translations
            );
        }

        return $categories;
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
     * @param string $name Fallback name
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
     * Save or update translation for a category
     *
     * @param int $categoryId
     * @param string $language Language code (e.g., 'en', 'de')
     * @param string $name Translated name
     * @return void
     */
    public function saveTranslation(int $categoryId, string $language, string $name): void
    {
        // Check if translation exists
        $exists = DB::table('news_category_translations')
            ->where('category_id', '=', $categoryId)
            ->where('language', '=', $language)
            ->exists();

        if ($exists) {
            // Update existing translation
            DB::table('news_category_translations')
                ->where('category_id', '=', $categoryId)
                ->where('language', '=', $language)
                ->update(['name' => $name]);
        } else {
            // Insert new translation
            DB::table('news_category_translations')->insert([
                'category_id' => $categoryId,
                'language' => $language,
                'name' => $name,
            ]);
        }
    }

    /**
     * Delete translation for a category
     *
     * @param int $categoryId
     * @param string $language Language code
     * @return void
     */
    public function deleteTranslation(int $categoryId, string $language): void
    {
        DB::table('news_category_translations')
            ->where('category_id', '=', $categoryId)
            ->where('language', '=', $language)
            ->delete();
    }

    /**
     * Delete all translations for a category
     *
     * @param int $categoryId
     * @return void
     */
    public function deleteAllTranslations(int $categoryId): void
    {
        DB::table('news_category_translations')
            ->where('category_id', '=', $categoryId)
            ->delete();
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
