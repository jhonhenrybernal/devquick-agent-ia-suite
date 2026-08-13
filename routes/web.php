<?php

use App\Http\Controllers\Automation\AgentController;
use App\Http\Controllers\Automation\AiProviderController;
use App\Http\Controllers\Automation\AutomationController;
use App\Http\Controllers\Automation\DolibarrController;
use App\Http\Controllers\Automation\TelegramController;
use App\Http\Controllers\Automation\TelegramWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::prefix('{current_team}/automation')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class.':owner'])
    ->name('automation.')
    ->group(function () {
        Route::get('/', [AutomationController::class, 'index'])->name('index');
        Route::get('dolibarr', [DolibarrController::class, 'edit'])->name('dolibarr.edit');
        Route::patch('dolibarr', [DolibarrController::class, 'update'])->name('dolibarr.update');
        Route::post('dolibarr/test', [DolibarrController::class, 'testConnection'])->name('dolibarr.test');
        Route::get('ai-provider', [AiProviderController::class, 'edit'])->name('ai-provider.edit');
        Route::patch('ai-provider', [AiProviderController::class, 'update'])->name('ai-provider.update');
        Route::post('ai-provider/test', [AiProviderController::class, 'testConnection'])->name('ai-provider.test');
        Route::get('ai-provider/stream', [AiProviderController::class, 'stream'])->name('ai-provider.stream');
        Route::get('telegram', [TelegramController::class, 'edit'])->name('telegram.edit');
        Route::get('telegram/inbox', [TelegramController::class, 'inbox'])->name('telegram.inbox');
        Route::patch('telegram', [TelegramController::class, 'update'])->name('telegram.update');
        Route::post('telegram/validate-token', [TelegramController::class, 'validateToken'])->name('telegram.validate-token');
        Route::post('telegram/detect-chat-id', [TelegramController::class, 'detectChatId'])->name('telegram.detect-chat-id');
        Route::post('telegram/sync-webhook', [TelegramController::class, 'syncWebhook'])->name('telegram.sync-webhook');
        Route::post('telegram/test', [TelegramController::class, 'testConnection'])->name('telegram.test');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('agents/{automation_agent}', [AgentController::class, 'show'])->name('agents.show');
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::patch('agents/{automation_agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{automation_agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    });

Route::post('{current_team}/automation/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:60,1')
    ->name('automation.telegram.webhook');

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
