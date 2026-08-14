<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $keepIds = DB::table('telegram_inbound_messages')
            ->selectRaw('MIN(id) as id')
            ->whereNotNull('update_id')
            ->groupBy('team_id', 'direction', 'update_id');

        DB::table('telegram_inbound_messages')
            ->whereNotNull('update_id')
            ->whereNotIn('id', $keepIds)
            ->delete();

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
