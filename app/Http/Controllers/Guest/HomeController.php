<?php

namespace App\Http\Controllers\Guest;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('guest.home', [
            'stats' => $this->stats(),
            'latestBooks' => Book::query()->active()
                ->with(['author', 'category'])
                ->latest()->latest('id')
                ->take(8)->get(),
            'popularBooks' => Book::query()->active()
                ->with(['author', 'category'])
                ->popular()->orderBy('title')
                ->take(4)->get(),
            'categories' => Category::query()
                ->withCount(['books' => fn ($query) => $query->active()])
                ->orderByDesc('books_count')->orderBy('name')
                ->take(8)->get(),
            'latestPosts' => Blog::query()->published()
                ->with('category')
                ->latest('published_at')
                ->take(3)->get(),
        ]);
    }

    public function about(): View
    {
        return view('guest.about', ['stats' => $this->stats()]);
    }

    public function contact(): View
    {
        return view('guest.contact');
    }

    /** @return array{titles:int, copies:int, members:int, categories:int} */
    private function stats(): array
    {
        return [
            'titles' => Book::query()->active()->count(),
            'copies' => (int) Book::query()->active()->sum('total_stock'),
            'members' => User::query()->role(Role::Anggota)->active()->count(),
            'categories' => Category::query()->has('books')->count(),
        ];
    }
}
