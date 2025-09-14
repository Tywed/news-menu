<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Add translations table for news categories (name only).
 */
class Migration3 implements MigrationInterface
{
    public function upgrade(): void
    {
        if (!DB::schema()->hasTable('news_category_translations')) {
            DB::schema()->create('news_category_translations', function (Blueprint $table): void {
                $table->integer('category_id');
                $table->string('language', 10)->charset('utf8')->collate('utf8_unicode_ci');
                $table->string('name', 255)->charset('utf8')->collate('utf8_unicode_ci');

                // Composite primary key ensures uniqueness per (category, language)
                $table->primary(['category_id', 'language']);
                $table->foreign('category_id')
                    ->references('category_id')
                    ->on('news_categories')
                    ->onDelete('CASCADE');
            });
        }
    }
}
