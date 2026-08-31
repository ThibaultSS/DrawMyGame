<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_drawing_id')->constrained()->cascadeOnDelete();

            $table->tinyInteger('value');

            $table->timestamps();

            $table->unique(['user_id', 'saved_drawing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_votes');
    }
};
