@props(['title', 'subtitle' => null])

<div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div>{{ $slot }}</div>
    @endif
</div>
