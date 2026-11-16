<?php

use App\Http\Controllers\AdminKaryawan\DashboardController as AdminKaryawanDashboardController;
use App\Http\Controllers\AdminMaster\AuthorController;
use App\Http\Controllers\AdminMaster\CategoryController;
use App\Http\Controllers\AdminMaster\DashboardController as AdminMasterDashboardController;
use App\Http\Controllers\AdminMaster\PublisherController;
use App\Http\Controllers\AdminMaster\ShelfController;
use App\Http\Controllers\Anggota\DashboardController as AnggotaDashboardController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Guest\BlogController;
use App\Http\Controllers\Guest\CatalogController;
use App\Http\Controllers\Guest\HomeController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminMaster\BookController;
use App\Http\Controllers\SuperAdmin\ActivityLogController;
use App\Http\Controllers\SuperAdmin\BlogController as SuperAdminBlogController;
use App\Http\Controllers\SuperAdmin\LibraryProfileController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\AdminKaryawan\BorrowingController as StaffBorrowingController;
use App\Http\Controllers\AdminKaryawan\MemberController;
use App\Http\Controllers\Anggota\BorrowingController as MemberBorrowingController;
use App\Http\Controllers\Anggota\CatalogController as MemberCatalogController;
use App\Http\Controllers\AdminKaryawan\FineController as StaffFineController;
use App\Http\Controllers\AdminKaryawan\ReturnController;
use App\Http\Controllers\Anggota\FineController as MemberFineController;
use App\Http\Controllers\AdminKaryawan\MemberCardController;
use App\Http\Controllers\Anggota\MembershipCardController;
use App\Http\Controllers\Guest\MembershipVerificationController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Halaman publik (Guest)
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tentang-kami', [HomeController::class, 'about'])->name('about');
Route::get('/kontak', [HomeController::class, 'contact'])->name('contact');

Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/katalog/{book}', [CatalogController::class, 'show'])->whereNumber('book')->name('catalog.show');

// Halaman yang dibuka saat QR kartu dipindai. Token 64 karakter, dibatasi 30 permintaan per menit.
Route::get('/verifikasi-kartu/{token}', MembershipVerificationController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('membership.verify');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

/*
|--------------------------------------------------------------------------
| Autentikasi (hanya untuk tamu)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1')->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    // Nama "password.reset" dipakai oleh email reset bawaan Laravel.
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Pintasan: arahkan ke dashboard sesuai role.
    // Route::get('/dashboard', fn() => redirect()->route(auth()->user()->role->dashboardRoute()))->name('dashboard');
        // Pintasan: arahkan ke dashboard sesuai role.
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Master data: dipakai bersama oleh Super Admin dan Admin Master
|--------------------------------------------------------------------------
*/
$masterData = function (): void {
    Route::resource('books', BookController::class)->parameters(['books' => 'book']);
    Route::resource('categories', CategoryController::class)->parameters(['categories' => 'category']);
    Route::resource('authors', AuthorController::class)->parameters(['authors' => 'author']);
    Route::resource('publishers', PublisherController::class)->parameters(['publishers' => 'publisher']);
    Route::resource('shelves', ShelfController::class)->parameters(['shelves' => 'shelf']);
};

/*
|--------------------------------------------------------------------------
| Layanan transaksi: dipakai bersama oleh Super Admin dan Admin Karyawan
|--------------------------------------------------------------------------
*/
$staffTransactions = function (): void {
    Route::get('borrowings/active', [StaffBorrowingController::class, 'active'])->name('borrowings.active');
    Route::post('borrowings/{borrowing}/approve', [StaffBorrowingController::class, 'approve'])->whereNumber('borrowing')->name('borrowings.approve');
    Route::post('borrowings/{borrowing}/reject', [StaffBorrowingController::class, 'reject'])->whereNumber('borrowing')->name('borrowings.reject');
    Route::get('borrowings', [StaffBorrowingController::class, 'index'])->name('borrowings.index');
    Route::get('borrowings/{borrowing}', [StaffBorrowingController::class, 'show'])->whereNumber('borrowing')->name('borrowings.show');

    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::get('members/{member}', [MemberController::class, 'show'])->whereNumber('member')->name('members.show');
    Route::get('borrowings/{borrowing}/receipt', [StaffBorrowingController::class, 'receipt'])->whereNumber('borrowing')->name('borrowings.receipt');

    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('returns/create/{borrowing}', [ReturnController::class, 'create'])->whereNumber('borrowing')->name('returns.create');
    Route::post('returns/create/{borrowing}', [ReturnController::class, 'store'])->whereNumber('borrowing')->name('returns.store');
    Route::get('returns/{returnTransaction}', [ReturnController::class, 'show'])->whereNumber('returnTransaction')->name('returns.show');
    Route::get('returns/{returnTransaction}/receipt', [ReturnController::class, 'receipt'])->whereNumber('returnTransaction')->name('returns.receipt');

    Route::get('fines', [StaffFineController::class, 'index'])->name('fines.index');
    Route::get('fines/{fine}', [StaffFineController::class, 'show'])->whereNumber('fine')->name('fines.show');
    Route::post('fines/{fine}/payments', [StaffFineController::class, 'pay'])->whereNumber('fine')->name('fines.pay');
    Route::post('fines/{fine}/waive', [StaffFineController::class, 'waive'])->whereNumber('fine')->name('fines.waive');

    Route::get('members/{member}/card', [MemberCardController::class, 'show'])->whereNumber('member')->name('members.card');
    Route::get('members/{member}/card/pdf', [MemberCardController::class, 'pdf'])->whereNumber('member')->name('members.card.pdf');
};

/*
|--------------------------------------------------------------------------
| Area per role (dilindungi middleware, bukan sekadar menu tersembunyi)
|--------------------------------------------------------------------------
*/
Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'active', 'role:super-admin'])
    ->group(function () use ($masterData, $staffTransactions) {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        $masterData();
        $staffTransactions();

        // Pengguna
        Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::resource('users', UserController::class)->parameters(['users' => 'user']);

        // Profil perpustakaan
        Route::get('library-profile', [LibraryProfileController::class, 'edit'])->name('library-profile.edit');
        Route::put('library-profile', [LibraryProfileController::class, 'update'])->name('library-profile.update');

        // Blog
        Route::post('blogs/{blog}/publish', [SuperAdminBlogController::class, 'publish'])->name('blogs.publish');
        Route::post('blogs/{blog}/unpublish', [SuperAdminBlogController::class, 'unpublish'])->name('blogs.unpublish');
        Route::resource('blogs', SuperAdminBlogController::class)->parameters(['blogs' => 'blog']);

        // Log aktivitas
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Laporan
        Route::get('reports/borrowings', [ReportController::class, 'borrowings'])->name('reports.borrowings');
        Route::get('reports/returns', [ReportController::class, 'returns'])->name('reports.returns');
        Route::get('reports/borrowings/export/{format}', [ReportController::class, 'exportBorrowings'])
            ->whereIn('format', ['excel', 'pdf', 'print'])->name('reports.borrowings.export');
        Route::get('reports/returns/export/{format}', [ReportController::class, 'exportReturns'])
            ->whereIn('format', ['excel', 'pdf', 'print'])->name('reports.returns.export');
    });

Route::prefix('admin-master')
    ->name('admin-master.')
    ->middleware(['auth', 'active', 'role:admin-master'])
    ->group(function () use ($masterData) {
        Route::get('/dashboard', [AdminMasterDashboardController::class, 'index'])->name('dashboard');
        $masterData();
    });

Route::prefix('admin-karyawan')
    ->name('admin-karyawan.')
    ->middleware(['auth', 'active', 'role:admin-karyawan'])
    ->group(function () use ($staffTransactions) {
        Route::get('/dashboard', [AdminKaryawanDashboardController::class, 'index'])->name('dashboard');
        $staffTransactions();
    });

Route::prefix('anggota')
    ->name('anggota.')
    ->middleware(['auth', 'active', 'role:anggota'])
    ->group(function () {
        Route::get('/dashboard', [AnggotaDashboardController::class, 'index'])->name('dashboard');

        Route::get('catalog', [MemberCatalogController::class, 'index'])->name('catalog.index');
        Route::get('catalog/{book}', [MemberCatalogController::class, 'show'])->whereNumber('book')->name('catalog.show');

        Route::get('borrowings', [MemberBorrowingController::class, 'index'])->name('borrowings.index');
        Route::get('borrowings/history', [MemberBorrowingController::class, 'history'])->name('borrowings.history');
        Route::post('borrowings', [MemberBorrowingController::class, 'store'])->name('borrowings.store');
        Route::get('borrowings/{borrowing}', [MemberBorrowingController::class, 'show'])->whereNumber('borrowing')->name('borrowings.show');
        Route::post('borrowings/{borrowing}/cancel', [MemberBorrowingController::class, 'cancel'])->whereNumber('borrowing')->name('borrowings.cancel');
        Route::get('fines', [MemberFineController::class, 'index'])->name('fines.index');
        Route::get('membership-card', [MembershipCardController::class, 'show'])->name('membership-card.show');
        Route::get('membership-card/pdf', [MembershipCardController::class, 'pdf'])->name('membership-card.pdf');
        Route::post('membership-card/regenerate', [MembershipCardController::class, 'regenerate'])->name('membership-card.regenerate');
    });

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
