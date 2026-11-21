{{--
 | Toasts arriba a la derecha (componente Alpine "toasts" en resources/js/app.js). Se disparan con
 |   $this->dispatch('toast', mensaje: '…', tipo: 'ok'|'error');
 | o tras un redirect con session()->flash('ok'|'error', '…'). Se van solos.
--}}
<div class="toasts no-print" aria-live="polite"
     x-data="toasts(@js(array_values(array_filter([session('ok') ? ['ok', session('ok')] : null, session('error') ? ['error', session('error')] : null]))))"
     x-on:toast.window="agregar($event.detail.mensaje, $event.detail.tipo ?? 'ok')">
    <template x-for="t in lista" :key="t.id">
        <div x-show="t.visible" x-transition:enter="anim-enter" x-transition:enter-start="anim-from-up" x-transition:leave="anim-leave" x-transition:leave-end="anim-from-up"
             class="toast" :class="t.tipo === 'error' ? 'toast-err' : 'toast-ok'" role="status">
            <span x-text="t.mensaje"></span>
            <button type="button" class="toast-x" @click="cerrar(t.id)" aria-label="Cerrar">✕</button>
        </div>
    </template>
</div>
