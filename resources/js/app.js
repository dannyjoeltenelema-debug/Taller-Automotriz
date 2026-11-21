/*
 | Componentes Alpine del panel. Viven aquí (archivo cacheado por el navegador) y no en el HTML
 | de cada página: así el layout pesa menos en cada navegación.
 | Alpine lo trae Livewire; se registran en "alpine:init".
 */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine

    // Menú persistente (@persist): marca el ítem activo según la URL (como wire:current: /panel/ordenes
    // cubre /panel/ordenes/nueva) y desliza el indicador con transform (la altura no se anima).
    Alpine.data('indicadorNav', () => ({
        y: 0,
        h: 0,
        visible: false,
        mover() {
            const actual = window.location.pathname.replace(/\/+$/, '').split('/')
            this.$el.querySelectorAll('a.nav-link[href]').forEach((a) => {
                const ruta = new URL(a.href).pathname.replace(/\/+$/, '').split('/')
                a.classList.toggle('active', ruta.every((seg, i) => seg === actual[i]))
            })
            const activo = this.$el.querySelector('.nav-link.active')
            if (! activo) { this.visible = false; return }
            this.y = activo.offsetTop
            this.h = activo.offsetHeight
            this.visible = true
        },
        get estilo() {
            return `transform: translateY(${this.y}px); height: ${this.h}px; opacity: ${this.visible ? 1 : 0}`
        },
    }))

    // Toasts arriba a la derecha que se van solos.
    Alpine.data('toasts', (iniciales = []) => ({
        lista: [],
        n: 0,
        init() {
            iniciales.forEach(([tipo, mensaje]) => this.agregar(mensaje, tipo))
        },
        agregar(mensaje, tipo = 'ok') {
            const id = ++this.n
            this.lista.push({ id, mensaje, tipo, visible: true })
            setTimeout(() => this.cerrar(id), tipo === 'error' ? 6000 : 3500)
        },
        cerrar(id) {
            const t = this.lista.find((x) => x.id === id)
            if (! t) return
            t.visible = false
            setTimeout(() => { this.lista = this.lista.filter((x) => x.id !== id) }, 200)
        },
    }))

    // Cerrar sesión sin <form> clásico: POST al action Logout y navegación de Livewire al portal.
    Alpine.data('cerrarSesion', (urlLogout, urlDestino) => ({
        cerrando: false,
        async salir() {
            if (this.cerrando) return
            this.cerrando = true
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            try {
                await fetch(urlLogout, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
            } finally {
                window.Livewire.navigate(urlDestino)
            }
        },
    }))
})

// Fila recién creada: resaltado tenue del acento que se apaga en ~1 s (clase .fila-nueva en movimiento.css).
// Livewire: $this->dispatch('fila-nueva', id: 'cli-15') y la fila lleva data-fila="cli-15".
window.addEventListener('fila-nueva', (e) => {
    const id = e.detail?.id
    if (! id) return
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const fila = document.querySelector(`[data-fila="${CSS.escape(id)}"]`)
        if (! fila) return
        fila.classList.remove('fila-nueva')
        void fila.offsetWidth // reinicia la animación si ya estaba
        fila.classList.add('fila-nueva')
        setTimeout(() => fila.classList.remove('fila-nueva'), 1300)
    }))
})
