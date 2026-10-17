<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('players', function (Blueprint $table) {
            $table->string('school_origin')->nullable();
            $table->string('birth_place_date')->nullable();
        });
        // Preserve existing dates as text; birthplace can be completed in the form.
        DB::table('players')->whereNotNull('birth_date')->update(['birth_place_date' => DB::raw('birth_date')]);
        Schema::dropIfExists('player_stats');
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['height', 'weight', 'position', 'overall_rating', 'birth_date']);
        });
    }
    public function down(): void {
        Schema::table('players', function (Blueprint $table) {
            $table->integer('height')->nullable();
            $table->integer('weight')->nullable();
            $table->string('position')->default('Forward');
            $table->decimal('overall_rating', 4, 2)->default(6.00);
            $table->date('birth_date')->nullable();
            $table->dropColumn(['school_origin', 'birth_place_date']);
        });
        // Recreates the old schema only; deleted values require a backup restore.
        $migration = require database_path('migrations/2026_07_27_035254_create_player_stats_table.php');
        $migration->up();
    }
};
