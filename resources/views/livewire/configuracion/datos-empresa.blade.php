<div>
    <div class="mb-3">
        <h1 class="page-title">Datos del taller</h1>
        <div class="page-sub">Se usan en el encabezado del panel, el PDF de la OT, la etiqueta QR y el botón de WhatsApp del portal</div>
    </div>

    <form class="panel max-w-[720px]" wire:submit="guardar">
        <div class="panel-body space-y-2">
            <div class="form-row"><label class="lbl">Razón social <span class="req">*</span></label><div><input class="inp" wire:model="form.razon_social">@error('form.razon_social')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">RUC <span class="req">*</span></label><div><input class="inp" wire:model="form.ruc" maxlength="13">@error('form.ruc')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">IVA (%) <span class="req">*</span></label><div><input type="number" step="0.01" class="inp w-28" wire:model="form.iva_porcentaje">@error('form.iva_porcentaje')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Teléfono / WhatsApp</label><div><input class="inp" wire:model="form.telefono">@error('form.telefono')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Correo</label><div><input class="inp" wire:model="form.email">@error('form.email')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Dirección</label><div><input class="inp" wire:model="form.direccion"></div></div>
        </div>
        <div class="modal-foot"><span class="muted text-[12px] mr-auto" wire:dirty>Cambios sin guardar</span><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="guardar"><span wire:loading.remove wire:target="guardar">Guardar</span><span wire:loading wire:target="guardar">Guardando…</span></button></div>
    </form>
</div>
