<div>
    <div class="mb-3">
        <h1 class="page-title">Datos del taller</h1>
        <div class="page-sub">Se usan en el encabezado del panel, el PDF de la OT, la etiqueta QR y el botón de WhatsApp del portal</div>
    </div>
    @if ($aviso)<div class="alert alert-ok mb-3">{{ $aviso }}</div>@endif

    <form class="panel max-w-[720px]" wire:submit="guardar">
        <div class="panel-body space-y-2">
            <div class="form-row"><label class="lbl">Razón social <span class="req">*</span></label><div><input class="inp" wire:model="form.razon_social">@error('form.razon_social')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">RUC <span class="req">*</span></label><div><input class="inp" wire:model="form.ruc" maxlength="13">@error('form.ruc')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">IVA (%) <span class="req">*</span></label><div><input type="number" step="0.01" class="inp w-28" wire:model="form.iva_porcentaje">@error('form.iva_porcentaje')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Teléfono / WhatsApp</label><div><input class="inp" wire:model="form.telefono">@error('form.telefono')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Correo</label><div><input class="inp" wire:model="form.email">@error('form.email')<div class="err">{{ $message }}</div>@enderror</div></div>
            <div class="form-row"><label class="lbl">Dirección</label><div><input class="inp" wire:model="form.direccion"></div></div>
        </div>
        <div class="modal-foot"><button class="btn btn-primary">Guardar</button></div>
    </form>
</div>
