<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Update the database schema to add indexes for performance optimization
 */
class Migration4 implements MigrationInterface
{
    /**
     * Upgrade the database schema
     */
    public function upgrade(): void
    {
        // Add indexes to news table for frequently used queries
        if (DB::schema()->hasTable('news')) {
            // Index on gedcom_id (used in almost all queries)
            if (!$this->hasIndex('news', 'news_gedcom_id_index')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->index('gedcom_id', 'news_gedcom_id_index');
                });
            }

            // Index on category_id (used for filtering by category)
            if (!$this->hasIndex('news', 'news_category_id_index')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->index('category_id', 'news_category_id_index');
                });
            }

            // Composite index for sorting (is_pinned, updated) - most common query pattern
            if (!$this->hasIndex('news', 'news_pinned_updated_index')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->index(['is_pinned', 'updated'], 'news_pinned_updated_index');
                });
            }

            // Index on updated for sorting by date
            if (!$this->hasIndex('news', 'news_updated_index')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->index('updated', 'news_updated_index');
                });
            }

            // Index on view_count for popular news queries
            if (!$this->hasIndex('news', 'news_view_count_index')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->index('view_count', 'news_view_count_index');
                });
            }
        }

        // Add indexes to news_comments table
        if (DB::schema()->hasTable('news_comments')) {
            // Index on news_id (used in all comment queries)
            if (!$this->hasIndex('news_comments', 'news_comments_news_id_index')) {
                DB::schema()->table('news_comments', function (Blueprint $table): void {
                    $table->index('news_id', 'news_comments_news_id_index');
                });
            }

            // Index on updated for sorting comments by date
            if (!$this->hasIndex('news_comments', 'news_comments_updated_index')) {
                DB::schema()->table('news_comments', function (Blueprint $table): void {
                    $table->index('updated', 'news_comments_updated_index');
                });
            }
        }

        // Add indexes to news_likes table
        if (DB::schema()->hasTable('news_likes')) {
            // Index on news_id (used in like count queries)
            if (!$this->hasIndex('news_likes', 'news_likes_news_id_index')) {
                DB::schema()->table('news_likes', function (Blueprint $table): void {
                    $table->index('news_id', 'news_likes_news_id_index');
                });
            }
        }

        // Add indexes to comments_likes table
        if (DB::schema()->hasTable('comments_likes')) {
            // Index on comments_id (used in like count queries)
            if (!$this->hasIndex('comments_likes', 'comments_likes_comments_id_index')) {
                DB::schema()->table('comments_likes', function (Blueprint $table): void {
                    $table->index('comments_id', 'comments_likes_comments_id_index');
                });
            }
        }
    }

    /**
     * Check if an index exists on a table
     *
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            // If table doesn't exist or error, assume index doesn't exist
            return false;
        }
    }
}
