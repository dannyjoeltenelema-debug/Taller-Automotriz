<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'cedula' => Auth::user()->cedula,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña es incorrecta.',
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-auth-header title="Confirmar contraseña" description="Esta es una zona protegida. Confirme su contraseña para continuar." />

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="confirmPassword" class="flex flex-col gap-3" novalidate>
        <x-campo label="Contraseña" for="password" error="password">
            <input id="password" type="password" class="inp" wire:model="password" name="password" autocomplete="current-password" autofocus required>
        </x-campo>

        <button type="submit" class="btn btn-primary mt-1">Confirmar</button>
    </form>
</div>
