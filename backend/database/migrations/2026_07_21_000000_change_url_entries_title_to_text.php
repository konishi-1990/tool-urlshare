<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * title は OGP から取得するため、サイトによっては 500 文字を超える。
     * varchar(500) だと pgsql が "value too long" (SQLSTATE 22001) で
     * INSERT を失敗させ URL 追加が 500 になる（sqlite は長さ非強制のため
     * 開発環境では再現しない）。長さ制限のない text に変更する。
     */
    public function up(): void
    {
        Schema::table('url_entries', function (Blueprint $table) {
            $table->text('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('url_entries', function (Blueprint $table) {
            $table->string('title', 500)->nullable()->change();
        });
    }
};
