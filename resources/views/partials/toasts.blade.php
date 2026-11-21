{{--
 | Toasts arriba a la derecha. Se disparan desde Livewire con
 |   $this->dispatch('toast', mensaje: '…', tipo: 'ok'|'error');
 | y desde un redirect con session()->flash('ok'|'error', '…'). Se van solos.
--}}
<div class="toasts no-print" aria-live="polite"
     x-data="{
        lista: [], n: 0,
        agregar(mensaje, tipo = 'ok') {
            const id = ++this.n;
            this.lista.push({ id, mensaje, tipo, visible: true });
            setTimeout(() => this.cerrar(id), tipo === 'error' ? 6000 : 3500);
        },
        cerrar(id) {
            const t = this.lista.find(t => t.id === id);
            if (t) { t.visible = false; setTimeout(() => this.lista = this.lista.filter(x => x.id !== id), 200); }
        },
     }"
     x-on:toast.window="agregar($event.detail.mensaje, $event.detail.tipo ?? 'ok')"
     x-init="
        @if (session('ok')) agregar(@js(session('ok')), 'ok'); @endif
        @if (session('error')) agregar(@js(session('error')), 'error'); @endif
     ">
    <template x-for="t in lista" :key="t.id">
        <div x-show="t.visible" x-transition:enter="anim-enter" x-transition:enter-start="anim-from-up" x-transition:leave="anim-leave" x-transition:leave-end="anim-from-up"
             class="toast" :class="t.tipo === 'error' ? 'toast-err' : 'toast-ok'" role="status">
            <span x-text="t.mensaje"></span>
            <button type="button" class="toast-x" @click="cerrar(t.id)" aria-label="Cerrar">✕</button>
        </div>
    </template>
</div>
