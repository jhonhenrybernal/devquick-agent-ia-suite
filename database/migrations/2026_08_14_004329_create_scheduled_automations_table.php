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
        Schema::create('scheduled_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_message_id')
                ->nullable()
                ->constrained('telegram_inbound_messages')
                ->nullOnDelete();
            $table->foreignId('parent_agent_id')
                ->nullable()
                ->constrained('automation_agents')
                ->nullOnDelete();
            $table->foreignId('child_agent_id')
                ->nullable()
                ->constrained('automation_agents')
                ->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('trigger_type', 20)->default('interval')->index();
            $table->string('cron_expression')->nullable();
            $table->unsignedInteger('interval_value')->nullable();
            $table->string('interval_unit', 20)->nullable();
            $table->string('timezone', 100)->default(config('app.timezone'));
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->text('last_result')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_automations');
    }
};
