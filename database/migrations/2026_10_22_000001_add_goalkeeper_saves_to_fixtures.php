<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fixtures', fn (Blueprint $table) => $table->json('goalkeeper_saves')->nullable());
    }

    public function down(): void
    {
        Schema::table('fixtures', fn (Blueprint $table) => $table->dropColumn('goalkeeper_saves'));
    }
};
