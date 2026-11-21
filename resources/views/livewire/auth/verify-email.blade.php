<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-auth-header title="Verificar correo" description="Revise su bandeja de entrada y abra el enlace de verificación que le enviamos." />

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-ok">Se envió un nuevo enlace de verificación a su correo.</div>
    @endif

    <button type="button" wire:click="sendVerification" class="btn btn-primary">Reenviar correo de verificación</button>

    <div class="text-center">
        <button type="button" wire:click="logout" class="link text-[12.5px]">Cerrar sesión</button>
    </div>
</div>
