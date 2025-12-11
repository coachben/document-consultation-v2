<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->string('type')->default('note'); // 'note', 'highlight'
            $table->json('meta')->nullable(); // For storing rects, color, etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            //
        });
    }
};
