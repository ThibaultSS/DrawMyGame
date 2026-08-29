<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The leaderboard asks for one drawing's rows, fastest first.
     *
     * The table's other index starts with user_id, which cannot serve that, and
     * the foreign key's own index on saved_drawing_id stops short of the
     * ordering — so every leaderboard sorted whatever it found. This covers
     * both halves of the query, and the times grow while the levels do not.
     */
    public function up(): void
    {
        Schema::table('level_plays', function (Blueprint $table) {
            $table->index(['saved_drawing_id', 'best_time_ms']);
        });
    }

    public function down(): void
    {
        Schema::table('level_plays', function (Blueprint $table) {
            $table->dropIndex(['saved_drawing_id', 'best_time_ms']);
        });
    }
};
