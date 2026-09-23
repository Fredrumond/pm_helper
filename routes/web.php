<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VersoesController;
use App\Livewire\AdminLlmModels;
use App\Livewire\AdminProjects;
use App\Livewire\AdminPromptModels;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('conversations.index'));

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard redireciona para conversas
    Route::get('/dashboard', fn () => redirect()->route('conversations.index'))->name('dashboard');

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    // Cards
    Route::get('/cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show');

    Route::middleware('admin')->group(function () {
        Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics.index');
        Route::get('/versoes', [VersoesController::class, 'index'])->name('versoes.index');
        Route::get('/admin/projetos', AdminProjects::class)->name('admin.projects.index');
        Route::get('/admin/prompts', AdminPromptModels::class)->name('admin.prompts.index');
        Route::get('/admin/modelos', AdminLlmModels::class)->name('admin.models.index');
    });

});

require __DIR__.'/auth.php';
