<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('excerpt', 500);
            $table->string('cover_image', 2048);
            $table->string('category', 30)->index();
            $table->text('content');
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
