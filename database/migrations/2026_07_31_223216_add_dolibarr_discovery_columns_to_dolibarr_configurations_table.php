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
        Schema::table('dolibarr_configurations', function (Blueprint $table) {
            $table->json('discovered_apis')->nullable()->after('api_url');
            $table->timestamp('last_discovered_at')->nullable()->after('discovered_apis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dolibarr_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'discovered_apis',
                'last_discovered_at',
            ]);
        });
    }
};
