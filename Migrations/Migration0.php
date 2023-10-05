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
    public function upgrade(): void
    {
        
        if (!DB::schema()->hasColumn('news', 'brief')) {
            DB::schema()->table('news', static function (Blueprint $table): void {
                $table->text('brief')->charset('utf8')->collation('utf8_unicode_ci');
                $table->string('media_id', 20)->charset('utf8')->collation('utf8_unicode_ci');
            });
        }

        if (!DB::schema()->hasTable('news_likes')) {
            DB::schema()->create('news_likes', static function(Blueprint $table): void {
                $table->unsignedInteger('news_id');
                $table->unsignedInteger('user_id');

                $table->foreign('news_id')->references('news_id')->on('news');
                $table->foreign('user_id')->references('user_id')->on('user');
            });
        }

        if (!DB::schema()->hasTable('news_comments')) {
            DB::schema()->create('news_comments', static function (Blueprint $table): void  {
                $table->increments('comments_id');
                $table->unsignedInteger('news_id');
                $table->unsignedInteger('user_id');
                $table->text('comment');

                $table->foreign('news_id')->references('news_id')->on('news');
                $table->foreign('user_id')->references('user_id')->on('users');

                $table->timestamp('updated')->default(DB::raw('CURRENT_TIMESTAMP'));
            });
        }

        if (!DB::schema()->hasTable('comments_likes')) {
            DB::schema()->create('comments_likes', static function (Blueprint $table): void  {
                $table->unsignedInteger('comments_id');
                $table->unsignedInteger('user_id');

                $table->foreign('news_id')->references('news_id')->on('news_comments');
                $table->foreign('user_id')->references('user_id')->on('user');
            });
        }
    }
}
