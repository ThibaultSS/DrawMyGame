<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per person per drawing: how often they have tried it and, if they
     * ever finished it, how fast.
     *
     * One row rather than a row per play. That is all the questions need: how
     * many people beat this level is a count of rows with a time, how hard it is
     * is that over the count of all rows, the leaderboard is an order by, and
     * your own best is your own row. A row per attempt would answer the same
     * things while growing without limit.
     */
    public function up(): void
    {
        Schema::create('level_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_drawing_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('attempts')->default(0);

            // Null until it is beaten, which is exactly what separates "tried
            // it" from "finished it" in every query below.
            $table->unsignedInteger('best_time_ms')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // One row per person per drawing, in the database and not only in
            // the controller: two tabs would otherwise each insert one and the
            // counts would drift.
            $table->unique(['user_id', 'saved_drawing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_plays');
    }
};
