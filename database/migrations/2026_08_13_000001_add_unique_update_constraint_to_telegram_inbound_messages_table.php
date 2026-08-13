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
            $table->unique(['team_id', 'direction', 'update_id'], 'telegram_inbound_messages_team_direction_update_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_inbound_messages', function (Blueprint $table): void {
            $table->dropUnique('telegram_inbound_messages_team_direction_update_id_unique');
        });
    }
};
