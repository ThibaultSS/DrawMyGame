<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_drawing_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('attempts')->default(0);

            $table->unsignedInteger('best_time_ms')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'saved_drawing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_plays');
    }
};
