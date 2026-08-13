<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A published drawing used to be an image and an author's name, which says
     * nothing about what the level is. Nullable on purpose: drawings published
     * before this migration have neither, and an unpublished drawing needs
     * neither — the gallery falls back to "Untitled".
     */
    public function up(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->string('title', 80)->nullable();
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
