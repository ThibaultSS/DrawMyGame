<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A saved drawing used to be only an image: every replay meant picking the
     * four colours again, and the speed and jump the author tuned were lost.
     * Nullable on purpose — drawings saved before this migration have none of
     * it, and the play flow falls back to colour picking for those.
     */
    public function up(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->string('platform_color', 7)->nullable();
            $table->string('goal_color', 7)->nullable();
            $table->string('player_color', 7)->nullable();
            $table->string('hazard_color', 7)->nullable();
            $table->unsignedTinyInteger('speed')->nullable();
            $table->unsignedTinyInteger('jump_height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->dropColumn([
                'platform_color',
                'goal_color',
                'player_color',
                'hazard_color',
                'speed',
                'jump_height',
            ]);
        });
    }
};
