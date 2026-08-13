<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation_agents', function (Blueprint $table) {
            $table->foreignId('parent_agent_id')
                ->nullable()
                ->after('team_id')
                ->constrained('automation_agents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_agent_id');
        });
    }
};
