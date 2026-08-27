<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per person per drawing: a level somebody else made, kept to play
     * again.
     *
     * This replaces copying. Saving someone else's level used to create a
     * drawing you owned — and could publish under your own name — which is not
     * what "save this to play later" means to anyone.
     */
    public function up(): void
    {
        Schema::create('level_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_drawing_id')->constrained()->cascadeOnDelete();

            // Your own feel for someone else's level, which is what copying it
            // used to buy. Nullable: favouriting without touching the sliders
            // means "however the author left it".
            $table->unsignedTinyInteger('speed')->nullable();
            $table->unsignedTinyInteger('jump_height')->nullable();

            $table->timestamps();

            // One per person per drawing, in the database rather than only in
            // the controller: two tabs would otherwise each insert a row.
            $table->unique(['user_id', 'saved_drawing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_favourites');
    }
};
