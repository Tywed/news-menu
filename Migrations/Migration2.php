<?php

namespace Tywed\Webtrees\Module\NewsMenu\Migrations;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Upgrade the database schema from version 1 to 2
 */
class Migration2
{
    /**
     * Upgrade to version 2
     *
     * @return void
     */
    public function upgrade(): void
    {
        // Добавляем поле languages для новостей
        if (!DB::schema()->hasColumn('news', 'languages')) {
            DB::schema()->table('news', function (Blueprint $table): void {
                $table->string('languages', 255)->default('')->after('category_id');
            });
        }
    }
} 