@props(['label', 'for' => null, 'req' => false, 'error' => null])
<div {{ $attributes->merge(['class' => '']) }}>
    <label class="lbl" @if($for) for="{{ $for }}" @endif>{{ $label }} @if($req)<span class="req">*</span>@endif</label>
    {{ $slot }}
    @if ($error)
        @error($error) <div class="err">{{ $message }}</div> @enderror
    @endif
</div>
