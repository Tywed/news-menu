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