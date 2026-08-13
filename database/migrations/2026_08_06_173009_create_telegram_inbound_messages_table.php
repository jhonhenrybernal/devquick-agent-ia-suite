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
        Schema::create('telegram_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('update_id')->nullable()->index();
            $table->string('update_type', 100)->nullable()->index();
            $table->string('chat_id', 100)->nullable()->index();
            $table->string('from_user_id', 100)->nullable();
            $table->string('from_username', 255)->nullable();
            $table->text('message_text')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_inbound_messages');
    }
};
