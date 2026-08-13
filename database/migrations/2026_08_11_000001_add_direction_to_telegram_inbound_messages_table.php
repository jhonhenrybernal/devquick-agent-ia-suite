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
        Schema::table('telegram_inbound_messages', function (Blueprint $table): void {
            $table->string('direction', 20)->default('inbound')->index()->after('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_inbound_messages', function (Blueprint $table): void {
            $table->dropColumn('direction');
        });
    }
};
