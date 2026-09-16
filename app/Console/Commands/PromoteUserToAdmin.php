<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('user:promote-admin {email}')]
#[Description('Promove um usuário já cadastrado ao papel de admin')]
class PromoteUserToAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Nenhum usuário encontrado com o e-mail [{$email}].");

            return self::FAILURE;
        }

        $user->role = UserRole::Admin;
        $user->save();

        Log::info('Usuário promovido a admin.', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        $this->info("Usuário [{$user->email}] promovido a admin.");

        return self::SUCCESS;
    }
}
