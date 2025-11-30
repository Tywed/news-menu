<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Update the database schema to add the necessary tables and fields
 */
class Migration0 implements MigrationInterface
{
    /**
     * Upgrade the database schema
     */
    public function upgrade(): void
    {
        // Проверяем, существует ли таблица news, и создаем ее при необходимости
        if (!DB::schema()->hasTable('news')) {
            DB::schema()->create('news', function (Blueprint $table): void {
                $table->integer('news_id', true);
                $table->integer('user_id');
                $table->integer('gedcom_id');
                $table->string('subject', 255)->charset('utf8')->collate('utf8_unicode_ci');
                $table->text('body')->charset('utf8')->collate('utf8_unicode_ci');
                $table->timestamp('updated')->useCurrent();
                $table->string('brief', 600)->charset('utf8')->collate('utf8_unicode_ci')->default('');
                $table->string('media_id', 20)->charset('utf8')->collate('utf8_unicode_ci')->default('');
            });
        } else {
            // Проверяем наличие колонок и добавляем их при необходимости
            if (!DB::schema()->hasColumn('news', 'user_id')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->integer('user_id')->default(0)->after('news_id');
                });
            }
            
            if (!DB::schema()->hasColumn('news', 'brief')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->string('brief', 600)->charset('utf8')->collate('utf8_unicode_ci')->default('');
                });
            }
            
            if (!DB::schema()->hasColumn('news', 'media_id')) {
                DB::schema()->table('news', function (Blueprint $table): void {
                    $table->string('media_id', 20)->charset('utf8')->collate('utf8_unicode_ci')->default('');
                });
            }
        }
        
        // Создаем таблицу news_likes если её нет
        if (!DB::schema()->hasTable('news_likes')) {
            DB::schema()->create('news_likes', function (Blueprint $table): void {
                $table->integer('news_id');
                $table->integer('user_id');
                $table->primary(['news_id', 'user_id']);
                $table->foreign('news_id')->references('news_id')->on('news')->onDelete('CASCADE');
                $table->foreign('user_id')->references('user_id')->on('user')->onDelete('CASCADE');
            });
        }
        
        // Создаем таблицу news_comments если её нет
        if (!DB::schema()->hasTable('news_comments')) {
            DB::schema()->create('news_comments', function (Blueprint $table): void {
                $table->integer('comments_id', true);
                $table->integer('news_id');
                $table->integer('user_id');
                $table->text('comment')->nullable();
                $table->timestamp('updated')->useCurrent();
                $table->foreign('news_id')->references('news_id')->on('news')->onDelete('CASCADE');
                $table->foreign('user_id')->references('user_id')->on('user')->onDelete('CASCADE');
            });
        }
        
        // Создаем таблицу comments_likes если её нет
        if (!DB::schema()->hasTable('comments_likes')) {
            DB::schema()->create('comments_likes', function (Blueprint $table): void {
                $table->integer('comments_id');
                $table->integer('user_id');
                $table->primary(['comments_id', 'user_id']);
                $table->foreign('comments_id')->references('comments_id')->on('news_comments')->onDelete('CASCADE');
                $table->foreign('user_id')->references('user_id')->on('user')->onDelete('CASCADE');
            });
        }
    }
}
