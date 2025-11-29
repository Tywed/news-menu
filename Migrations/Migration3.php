<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Update the database schema to add category translations support
 */
class Migration3 implements MigrationInterface
{
    /**
     * Upgrade the database schema
     */
    public function upgrade(): void
    {
        // Create news_category_translations table
        if (!DB::schema()->hasTable('news_category_translations')) {
            DB::schema()->create('news_category_translations', function (Blueprint $table): void {
                $table->integer('translation_id', true);
                $table->integer('category_id');
                $table->string('language', 5)->charset('utf8')->collate('utf8_unicode_ci');
                $table->string('name', 255)->charset('utf8')->collate('utf8_unicode_ci');

                // Foreign key with CASCADE delete
                $table->foreign('category_id')->references('category_id')->on('news_categories')->onDelete('CASCADE');

                // Unique constraint: one translation per category per language
                // This prevents duplicate translations for the same language
                $table->unique(['category_id', 'language'], 'category_language_unique');

                // Indexes for performance
                $table->index('category_id'); // Fast lookup by category
                $table->index('language');   // Fast lookup by language (for fallback queries)
            });
        }

        // Migrate existing category data to translations (default to 'en')
        // This will preserve existing categories as English translations
        if (DB::schema()->hasTable('news_categories') && DB::schema()->hasTable('news_category_translations')) {
            $categories = DB::table('news_categories')->get();

            foreach ($categories as $category) {
                // Check if translation already exists
                $exists = DB::table('news_category_translations')
                    ->where('category_id', '=', $category->category_id)
                    ->where('language', '=', 'en')
                    ->exists();

                if (!$exists) {
                    DB::table('news_category_translations')->insert([
                        'category_id' => $category->category_id,
                        'language' => 'en',
                        'name' => $category->name,
                    ]);
                }
            }
        }

        // Note: We keep 'name' and 'description' in parent table for backward compatibility
        // 'name' can be used as fallback if translation is missing
        // 'description' stays in parent table as it's not translated
    }
}


