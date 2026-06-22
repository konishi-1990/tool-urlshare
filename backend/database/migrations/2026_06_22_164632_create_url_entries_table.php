<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('status', 20)->default('temporary');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE url_entries ADD CONSTRAINT url_entries_status_check
                CHECK (status IN ('temporary', 'bookmarked', 'deleted'))");
        }

        Schema::table('url_entries', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_url_entries_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_entries');
    }
};
