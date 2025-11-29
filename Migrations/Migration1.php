<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Update the database schema to add categories, view counts, and pinned news support
 */
class Migration1 implements MigrationInterface
{
    /**
     * Upgrade the database schema
     */
    public function upgrade(): void
    {
        // Create news categories table
        if (!DB::schema()->hasTable('news_categories')) {
            DB::schema()->create('news_categories', function (Blueprint $table): void {
                $table->integer('category_id', true);
                $table->string('name', 255)->charset('utf8')->collate('utf8_unicode_ci');
                $table->string('description', 1000)->charset('utf8')->collate('utf8_unicode_ci')->nullable();
                $table->integer('sort_order')->default(0);
            });

            // Add default categories
            DB::table('news_categories')->insert([
                ['name' => 'Announcements', 'description' => 'Important announcements about the site or family', 'sort_order' => 1],
                ['name' => 'Events', 'description' => 'Upcoming family events and gatherings', 'sort_order' => 2],
                ['name' => 'Discoveries', 'description' => 'New genealogical discoveries and breakthroughs', 'sort_order' => 3],
                ['name' => 'General', 'description' => 'General news and updates', 'sort_order' => 4],
            ]);
        }

        // Add fields to news table
        if (!DB::schema()->hasColumn('news', 'category_id')) {
            DB::schema()->table('news', function (Blueprint $table): void {
                $table->integer('category_id')->nullable()->default(null);
                $table->foreign('category_id')->references('category_id')->on('news_categories')->onDelete('SET NULL');
            });
        }

        if (!DB::schema()->hasColumn('news', 'is_pinned')) {
            DB::schema()->table('news', function (Blueprint $table): void {
                $table->boolean('is_pinned')->default(false);
            });
        }

        if (!DB::schema()->hasColumn('news', 'view_count')) {
            DB::schema()->table('news', function (Blueprint $table): void {
                $table->integer('view_count')->default(0);
            });
        }
    }
}
