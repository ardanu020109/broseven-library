@php
    $socialIcons = [
        'instagram' => 'bi-instagram',
        'facebook' => 'bi-facebook',
        'youtube' => 'bi-youtube',
        'twitter' => 'bi-twitter-x',
        'x' => 'bi-twitter-x',
        'tiktok' => 'bi-tiktok',
        'whatsapp' => 'bi-whatsapp',
    ];
    $socialLinks = collect($libraryProfile->social_media ?? [])
        ->filter(fn ($url, $network) => isset($socialIcons[$network])
            && is_string($url)
            && Str::startsWith($url, ['http://', 'https://']));
@endphp

@if ($socialLinks->isNotEmpty())
    <div class="social-links d-flex gap-2">
        @foreach ($socialLinks as $network => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
               aria-label="{{ ucfirst($network) }}" title="{{ ucfirst($network) }}">
                <i class="bi {{ $socialIcons[$network] }}"></i>
            </a>
        @endforeach
    </div>
@endif
