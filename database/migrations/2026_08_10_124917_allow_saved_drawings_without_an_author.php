<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting an account used to take its levels with it, because the author
     * key cascaded. A level that has been published is out in the community
     * already — other people have played it and voted on it — so it stays, with
     * no author, and the gallery credits it to "Unknown publisher".
     *
     * Unpublished drafts are still deleted with the account; that is the
     * controller's job, not the constraint's.
     */
    public function up(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('saved_drawings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
