@props([
    'name', 'label', 'type' => 'text', 'value' => null, 'required' => false,
    'help' => null, 'rows' => 3, 'col' => 'col-12', 'maxlength' => null, 'placeholder' => null,
    'min' => null, 'max' => null, 'step' => null,
])

<div class="{{ $col }}">
    <label for="{{ $name }}" class="form-label fw-semibold">
        {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
                  class="form-control @error($name) is-invalid @enderror"
                  @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                  @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                  @if ($required) required @endif>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $value) }}"
               class="form-control @error($name) is-invalid @enderror"
               @if ($maxlength) maxlength="{{ $maxlength }}" @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($min !== null) min="{{ $min }}" @endif
               @if ($max !== null) max="{{ $max }}" @endif
               @if ($step !== null) step="{{ $step }}" @endif
               @if ($required) required @endif>
    @endif

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
