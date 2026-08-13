<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per person per drawing: a like or a dislike.
     */
    public function up(): void
    {
        Schema::create('drawing_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_drawing_id')->constrained()->cascadeOnDelete();

            // 1 or -1, so a drawing's standing is a plain sum() rather than two
            // counts that have to be subtracted in every query that ranks them.
            $table->tinyInteger('value');

            $table->timestamps();

            // One vote per person per drawing, enforced by the database and not
            // only by the controller: without it a double-submitted form or two
            // tabs would each insert a row and the count would drift.
            $table->unique(['user_id', 'saved_drawing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_votes');
    }
};
