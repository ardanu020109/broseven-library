@props(['icon', 'label', 'value', 'hint' => null, 'tone' => 'crimson'])

<div class="stat-tile tone-{{ $tone }}">
    <div class="stat-tile-icon"><i class="bi {{ $icon }}"></i></div>
    <div class="stat-tile-body">
        <div class="stat-tile-label">{{ $label }}</div>
        <div class="stat-tile-value">{{ $value }}</div>
        @if ($hint)
            <div class="stat-tile-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
