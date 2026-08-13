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
            $table->string('api_login')->nullable()->after('api_url');
            $table->text('api_password')->nullable()->after('api_login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dolibarr_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'api_login',
                'api_password',
            ]);
        });
    }
};
