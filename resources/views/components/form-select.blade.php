@props([
    'name', 'label', 'options' => [], 'value' => null, 'required' => false,
    'placeholder' => '-- Pilih --', 'col' => 'col-12', 'help' => null,
])

<div class="{{ $col }}">
    <label for="{{ $name }}" class="form-label fw-semibold">
        {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
    </label>

    <select id="{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror"
            @if ($required) required @endif>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $id => $text)
            <option value="{{ $id }}" @selected((string) old($name, $value) === (string) $id)>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
