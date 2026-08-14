<?php

use App\Models\Team;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('telegram inbound duplicate cleanup keeps the first record before the unique index is added', function () {
    $tableName = 'telegram_inbound_messages_cleanup_test';

    Schema::create($tableName, function (Blueprint $table): void {
        $table->id();
        $table->foreignId('team_id');
        $table->string('direction', 20);
        $table->unsignedBigInteger('update_id')->nullable();
        $table->string('update_type', 100)->nullable();
        $table->string('chat_id', 100)->nullable();
        $table->string('from_user_id', 100)->nullable();
        $table->string('from_username', 255)->nullable();
        $table->text('message_text')->nullable();
        $table->json('payload');
        $table->timestamps();
    });

    try {
        $team = Team::factory()->create();

        $firstDuplicateId = DB::table($tableName)->insertGetId([
            'team_id' => $team->id,
            'direction' => 'inbound',
            'update_id' => 570918853,
            'update_type' => 'message',
            'chat_id' => '7126235197',
            'from_user_id' => '7126235197',
            'from_username' => 'jhonh',
            'message_text' => 'Hola',
            'payload' => json_encode(['message' => ['text' => 'Hola']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($tableName)->insert([
            [
                'team_id' => $team->id,
                'direction' => 'inbound',
                'update_id' => 570918853,
                'update_type' => 'message',
                'chat_id' => '7126235197',
                'from_user_id' => '7126235197',
                'from_username' => 'jhonh',
                'message_text' => 'Hola otra vez',
                'payload' => json_encode(['message' => ['text' => 'Hola otra vez']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'team_id' => $team->id,
                'direction' => 'inbound',
                'update_id' => 570918854,
                'update_type' => 'message',
                'chat_id' => '7126235197',
                'from_user_id' => '7126235197',
                'from_username' => 'jhonh',
                'message_text' => 'Otro mensaje',
                'payload' => json_encode(['message' => ['text' => 'Otro mensaje']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'team_id' => $team->id,
                'direction' => 'inbound',
                'update_id' => null,
                'update_type' => 'message',
                'chat_id' => '7126235197',
                'from_user_id' => '7126235197',
                'from_username' => 'jhonh',
                'message_text' => 'Sin update id',
                'payload' => json_encode(['message' => ['text' => 'Sin update id']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $keepIds = DB::table($tableName)
            ->selectRaw('MIN(id) as id')
            ->whereNotNull('update_id')
            ->groupBy('team_id', 'direction', 'update_id');

        DB::table($tableName)
            ->whereNotNull('update_id')
            ->whereNotIn('id', $keepIds)
            ->delete();

        expect(DB::table($tableName)->count())->toBe(3);

        $remainingDuplicateIds = DB::table($tableName)
            ->where('direction', 'inbound')
            ->where('update_id', 570918853)
            ->pluck('id');

        expect($remainingDuplicateIds)->toHaveCount(1);
        expect($remainingDuplicateIds->first())->toBe($firstDuplicateId);
    } finally {
        Schema::dropIfExists($tableName);
    }
});
