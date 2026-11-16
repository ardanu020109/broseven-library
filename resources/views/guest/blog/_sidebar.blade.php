<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6 text-sienna mb-3">Cari Artikel</h2>
        <form method="GET" action="{{ route('blog.index') }}" role="search">
            <div class="input-group">
                <input type="search" name="q" value="{{ request('q') }}" maxlength="100"
                       class="form-control" placeholder="Kata kunci" aria-label="Cari artikel">
                <button class="btn btn-primary" type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6 text-sienna mb-3">Kategori</h2>
        <ul class="list-unstyled mb-0 side-list">
            @forelse ($categories as $category)
                <li>
                    <a href="{{ route('blog.index', ['kategori' => $category->slug]) }}">{{ $category->name }}</a>
                    <span class="badge text-bg-light">{{ $category->blogs_count }}</span>
                </li>
            @empty
                <li class="text-muted small">Belum ada kategori.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h6 text-sienna mb-3">Artikel Terbaru</h2>
        <ul class="list-unstyled mb-0 side-list">
            @forelse ($latestPosts as $item)
                <li class="d-block">
                    <a href="{{ route('blog.show', $item->slug) }}">{{ $item->title }}</a>
                    <div class="small text-muted">{{ $item->published_at?->translatedFormat('d F Y') }}</div>
                </li>
            @empty
                <li class="text-muted small">Belum ada artikel.</li>
            @endforelse
        </ul>
    </div>
</div>
