<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', 'Si la cuenta existe y tiene correo registrado, recibirá un enlace de recuperación.');
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-auth-header title="Recuperar contraseña" description="Ingrese el correo registrado en su cuenta y le enviaremos un enlace para restablecerla" />

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-3" novalidate>
        <x-campo label="Correo electrónico" for="email" error="email">
            <input id="email" type="email" class="inp" wire:model="email" name="email" autocomplete="email" autofocus required placeholder="correo@ejemplo.com">
        </x-campo>

        <button type="submit" class="btn btn-primary mt-1">Enviar enlace de recuperación</button>
    </form>

    <p class="muted text-[12px] text-center">Solo disponible para cuentas con correo registrado. Si no tiene uno, solicite el cambio al administrador.</p>

    <div class="text-center">
        <a href="{{ route('login') }}" class="link text-[12.5px]" wire:navigate>← Volver al acceso</a>
    </div>
</div>
