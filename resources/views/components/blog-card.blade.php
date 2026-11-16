@props(['post'])

<article class="card blog-card h-100">
    <a href="{{ route('blog.show', $post->slug) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $post->thumbnail_url }}" alt="" class="blog-thumb" loading="lazy">
    </a>
    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-2 d-flex flex-wrap align-items-center gap-2">
            @if ($post->category)
                <span class="badge-category">{{ $post->category->name }}</span>
            @endif
            <span><i class="bi bi-calendar3 me-1"></i>{{ $post->published_at?->translatedFormat('d F Y') }}</span>
        </div>
        <h3 class="h6 blog-title">
            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
        </h3>
        <p class="small text-muted mb-3">{{ Str::limit($post->excerpt ?: strip_tags($post->content), 110) }}</p>
        <a href="{{ route('blog.show', $post->slug) }}" class="mt-auto small fw-semibold">
            Baca selengkapnya <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</article>
