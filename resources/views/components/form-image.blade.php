@props([
    'name', 'label', 'current' => null, 'col' => 'col-12', 'removable' => false,
    'help' => 'Format JPG, PNG, atau WEBP. Maksimal 2 MB.',
])

<div class="{{ $col }}">
    <label for="{{ $name }}" class="form-label fw-semibold">{{ $label }}</label>
    <div class="d-flex align-items-start gap-3">
        <img id="{{ $name }}Preview" src="{{ $current }}" alt="" class="upload-preview {{ $current ? '' : 'd-none' }}">

        <div class="flex-grow-1">
            <input type="file" id="{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
                   class="form-control @error($name) is-invalid @enderror" data-preview="#{{ $name }}Preview">
            @error($name)
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">{{ $help }}</div>

            @if ($removable)
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_{{ $name }}" id="remove_{{ $name }}" value="1">
                    <label class="form-check-label" for="remove_{{ $name }}">Hapus {{ \Illuminate\Support\Str::lower($label) }} saat ini</label>
                </div>
            @endif
        </div>
    </div>
</div>
