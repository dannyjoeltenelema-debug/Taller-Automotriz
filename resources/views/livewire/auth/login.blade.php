<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Acceso del personal. El identificador ("Usuario") es la cédula, no el correo.
 */
new #[Layout('components.layouts.auth')] class extends Component {
    public string $cedula = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'cedula' => ['required', 'string', 'max:13'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'cedula.required' => 'Ingrese su usuario.',
            'cedula.max' => 'El usuario no puede tener más de 13 caracteres.',
            'password.required' => 'Ingrese su contraseña.',
        ];
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->cedula = trim($this->cedula);
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['cedula' => $this->cedula, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'cedula' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'cedula' => 'Demasiados intentos. Intente de nuevo en '.max(1, (int) ceil($seconds / 60)).' minuto(s).',
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->cedula).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-auth-header title="Acceso personal" description="Ingrese su usuario y contraseña para acceder al panel del taller" />

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-3" novalidate>
        <x-campo label="Usuario" for="cedula" error="cedula">
            <input id="cedula" type="text" class="inp" wire:model="cedula" name="cedula"
                   inputmode="numeric" autocomplete="username" autofocus required placeholder="Número de cédula">
        </x-campo>

        <x-campo label="Contraseña" for="password" error="password">
            <input id="password" type="password" class="inp" wire:model="password" name="password"
                   autocomplete="current-password" required>
        </x-campo>

        <div class="flex items-center justify-between gap-2">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="remember"> Recordarme
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="link text-[12.5px]" wire:navigate>¿Olvidó su contraseña?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary mt-1" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Verificando…</span>
        </button>
    </form>

    <div class="text-center">
        <a href="{{ route('home') }}" class="link text-[12.5px]" wire:navigate>← Volver al inicio</a>
    </div>
</div>
