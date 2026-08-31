<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
