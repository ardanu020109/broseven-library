<?php

namespace Tests\Feature;

use App\Exports\BorrowingExport;
use App\Exports\ReturnExport;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;
use App\Models\User;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    private User $staff;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['name' => 'Perpustakaan Uji', 'address' => 'Jl. Uji No. 1']);
        $this->staff = $this->makeStaff();
        $this->admin = $this->makeStaff('super-admin');

        // Rabu, 18 November 2026: minggu ini = 16 s.d. 22 November.
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    }

    // ------------------------------------------------------------------ helper

    private function loan(string $title, string $date, array $attributes = [], ?User $member = null, int $quantity = 1, int $returned = 0): Borrowing
    {
        $book = Book::factory()->withStock(5, 4)->create(['title' => $title]);

        return $this->makeBorrowed($member ?? $this->makeMember(), $this->staff, [$book], array_merge([
            'borrow_date' => $date,
            'requested_at' => $date . ' 09:00:00',
            'approved_at' => $date . ' 09:30:00',
            'due_date' => Carbon::parse($date)->addDays(7)->toDateString(),
        ], $attributes), $quantity, $returned);
    }

    private function returnRow(string $title, string $returnDate, int $daysLate, ?string $fineStatus, ?User $member = null): ReturnItem
    {
        $returned = Carbon::parse($returnDate);
        $book = Book::factory()->create(['title' => $title]);

        $borrowing = $this->makeBorrowed($member ?? $this->makeMember(), $this->staff, [$book], [
            'status' => 'returned',
            'borrow_date' => $returned->copy()->subDays(7 + $daysLate)->toDateString(),
            'due_date' => $returned->copy()->subDays($daysLate)->toDateString(),
        ], 1, 1);

        $return = ReturnTransaction::create([
            'code' => ReturnTransaction::generateCode(),
            'borrowing_id' => $borrowing->id,
            'return_date' => $returnDate,
            'processed_by' => $this->staff->id,
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'borrowing_item_id' => $borrowing->items()->firstOrFail()->id,
            'quantity' => 1,
            'due_date' => $returned->copy()->subDays($daysLate)->toDateString(),
            'days_late' => $daysLate,
            'fine_per_day' => 2000,
            'fine_amount' => $daysLate * 2000,
        ]);

        if ($fineStatus !== null) {
            Fine::create([
                'return_item_id' => $returnItem->id,
                'member_id' => $borrowing->member_id,
                'amount' => $daysLate * 2000,
                'paid_amount' => $fineStatus === 'paid' ? $daysLate * 2000 : 0,
                'status' => $fineStatus,
            ]);
        }

        return $returnItem;
    }

    /** @param array<int, string> $expected  @param array<int, string> $all */
    private function assertOnlyTitles(string $url, array $expected, array $all): void
    {
        $response = $this->actingAs($this->admin)->get($url)->assertOk();

        foreach ($all as $title) {
            in_array($title, $expected, true) ? $response->assertSee($title) : $response->assertDontSee($title);
        }
    }

    // ------------------------------------------------------------------ akses

    public function test_only_super_admin_can_open_reports_and_exports(): void
    {
        $urls = [
            '/super-admin/reports/borrowings',
            '/super-admin/reports/returns',
            '/super-admin/reports/borrowings/export/excel',
            '/super-admin/reports/borrowings/export/pdf',
            '/super-admin/reports/returns/export/print',
        ];

        foreach ([$this->makeStaff('admin-master'), $this->makeStaff('admin-karyawan'), $this->makeMember()] as $user) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }

        foreach ($urls as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')->assertOk();
        $this->actingAs($this->admin)->get('/super-admin/reports/returns')->assertOk();
    }

    public function test_unknown_export_format_is_not_found(): void
    {
        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/docx')->assertNotFound();
    }

    public function test_menu_items_for_reports_are_enabled(): void
    {
        $this->actingAs($this->admin)->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.reports.borrowings'), false)
            ->assertSee(route('super-admin.reports.returns'), false);
    }

    // ------------------------------------------------------------------ laporan peminjaman

    public function test_borrowing_report_has_the_required_columns_and_summary(): void
    {
        $this->loan('Buku Kolom', '2026-11-17', [], $this->makeMember(['name' => 'Anggota Kolom']), 2);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')
            ->assertOk()
            ->assertSeeInOrder(['No', 'Kode Peminjaman', 'Nama Anggota', 'Judul Buku', 'Jumlah', 'Tanggal Peminjaman', 'Tanggal Jatuh Tempo', 'Tanggal Pengembalian', 'Status'])
            ->assertSee('Anggota Kolom')
            ->assertSee('Buku Kolom')
            ->assertSee('17 Nov 2026')
            ->assertSee('24 Nov 2026')
            ->assertViewHas('summary', fn (array $s) => $s['transactions'] === 1 && $s['items'] === 1 && $s['books'] === 2);
    }

    public function test_default_period_is_this_month(): void
    {
        $this->loan('Buku Bulan Ini', '2026-11-03');
        $this->loan('Buku Bulan Lalu', '2026-10-20');

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')
            ->assertSee('Bulan ini (01 Nov 2026 s.d. 30 Nov 2026)')
            ->assertSee('Buku Bulan Ini')
            ->assertDontSee('Buku Bulan Lalu');
    }

    public function test_period_filters(): void
    {
        $dates = [
            'Buku Hari Ini' => '2026-11-18',
            'Buku Senin Ini' => '2026-11-16',
            'Buku Minggu Lalu' => '2026-11-15',   // Minggu: masih minggu sebelumnya
            'Buku Awal Bulan' => '2026-11-01',
            'Buku Bulan Lalu' => '2026-10-31',
            'Buku Awal Tahun' => '2026-01-01',
            'Buku Tahun Lalu' => '2025-12-31',
        ];

        foreach ($dates as $title => $date) {
            $this->loan($title, $date);
        }

        $all = array_keys($dates);

        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=today', ['Buku Hari Ini'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=week', ['Buku Hari Ini', 'Buku Senin Ini'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=month', ['Buku Hari Ini', 'Buku Senin Ini', 'Buku Minggu Lalu', 'Buku Awal Bulan'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=year', array_slice($all, 0, 6), $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=all', $all, $all);
    }

    public function test_date_range_filter_supports_open_ends_and_swapped_dates(): void
    {
        $dates = ['Buku A' => '2026-11-10', 'Buku B' => '2026-11-15', 'Buku C' => '2026-11-16', 'Buku D' => '2026-11-20'];

        foreach ($dates as $title => $date) {
            $this->loan($title, $date);
        }

        $all = array_keys($dates);

        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=range&from=2026-11-15&to=2026-11-16', ['Buku B', 'Buku C'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=range&from=2026-11-16', ['Buku C', 'Buku D'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=range&to=2026-11-10', ['Buku A'], $all);
        // Tanggal terbalik ditukar otomatis.
        $this->assertOnlyTitles('/super-admin/reports/borrowings?period=range&from=2026-11-16&to=2026-11-15', ['Buku B', 'Buku C'], $all);
    }

    public function test_pending_requests_use_their_request_date(): void
    {
        $member = $this->makeMember();
        $this->makePending($member, [Book::factory()->create(['title' => 'Buku Diajukan Bulan Ini'])], ['requested_at' => '2026-11-10 08:00:00']);
        $this->makePending($member, [Book::factory()->create(['title' => 'Buku Diajukan Bulan Lalu'])], ['requested_at' => '2026-10-10 08:00:00']);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings?period=month')
            ->assertSee('Buku Diajukan Bulan Ini')
            ->assertSee('Menunggu Persetujuan')
            ->assertDontSee('Buku Diajukan Bulan Lalu');

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings?period=year')->assertSee('Buku Diajukan Bulan Lalu');
    }

    public function test_member_and_book_filters(): void
    {
        $alfa = $this->makeMember(['name' => 'Anggota Alfa', 'username' => 'alfa.user']);
        $beta = $this->makeMember(['name' => 'Anggota Beta']);

        $this->loan('Buku Satu', '2026-11-10', [], $alfa);
        $this->loan('Buku Dua', '2026-11-11', [], $beta);

        $all = ['Buku Satu', 'Buku Dua'];

        $this->assertOnlyTitles('/super-admin/reports/borrowings?member=Alfa', ['Buku Satu'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?member=alfa.user', ['Buku Satu'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?book=Dua', ['Buku Dua'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?member=Alfa&book=Dua', [], $all);
    }

    public function test_status_filters_including_overdue_computed_from_due_date(): void
    {
        $member = $this->makeMember();
        $this->loan('Pinjam Aktif', '2026-11-16', ['due_date' => '2026-11-25']);
        $this->loan('Pinjam Telat', '2026-11-01', ['due_date' => '2026-11-10']); // status tersimpan masih "borrowed"
        $this->loan('Sudah Kembali', '2026-11-05', ['status' => 'returned'], $member, 1, 1);
        $this->makePending($member, [Book::factory()->create(['title' => 'Ditolak Petugas'])], ['status' => 'rejected', 'requested_at' => '2026-11-12 08:00:00']);

        $all = ['Pinjam Aktif', 'Pinjam Telat', 'Sudah Kembali', 'Ditolak Petugas'];

        $this->assertOnlyTitles('/super-admin/reports/borrowings?status=borrowed', ['Pinjam Aktif', 'Pinjam Telat'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?status=overdue', ['Pinjam Telat'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?status=returned', ['Sudah Kembali'], $all);
        $this->assertOnlyTitles('/super-admin/reports/borrowings?status=rejected', ['Ditolak Petugas'], $all);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')
            ->assertSee('Terlambat')   // baris yang lewat jatuh tempo
            ->assertSee('Dikembalikan');
    }

    public function test_return_date_and_per_book_status_are_shown(): void
    {
        $this->returnRow('Buku Sudah Kembali', '2026-11-17', 0, null);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings?period=all')
            ->assertSee('Buku Sudah Kembali')
            ->assertSee('17 Nov 2026')
            ->assertSee('Dikembalikan');
    }

    public function test_deleted_members_and_books_remain_in_the_history(): void
    {
        $member = $this->makeMember(['name' => 'Anggota Dihapus']);
        $borrowing = $this->loan('Buku Dihapus', '2026-11-10', ['status' => 'returned'], $member, 1, 1);

        $borrowing->items()->firstOrFail()->book->delete();
        $member->delete();

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')
            ->assertSee('Anggota Dihapus')
            ->assertSee('Buku Dihapus');
    }

    public function test_borrowing_report_is_paginated_with_filters_kept(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->loan("Buku Halaman {$i}", '2026-11-10');
        }

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings')
            ->assertSee('page-link')
            ->assertViewHas('paginator', fn ($p) => $p->count() === 15 && $p->total() === 16);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings?page=2&period=month')
            ->assertViewHas('paginator', fn ($p) => $p->count() === 1)
            ->assertViewHas('rows', fn ($rows) => $rows->first()['no'] === 16);
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->loan('Buku Tetap Tampil', '2026-11-10');

        $this->actingAs($this->admin)
            ->get('/super-admin/reports/borrowings?period=ngawur&status=xx&from=bukan&to=2026-99-99&return_status=x&fine_status=y&page=abc')
            ->assertOk()
            ->assertSee('Bulan ini')
            ->assertSee('Buku Tetap Tampil');
    }

    public function test_export_links_keep_the_selected_filters(): void
    {
        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings?period=week&member=Alfa')
            ->assertSee(str_replace('&', '&amp;', route('super-admin.reports.borrowings.export', ['format' => 'pdf', 'period' => 'week', 'member' => 'Alfa'])), false)
            ->assertSee(str_replace('&', '&amp;', route('super-admin.reports.borrowings.export', ['format' => 'excel', 'period' => 'week', 'member' => 'Alfa'])), false);
    }

    // ------------------------------------------------------------------ laporan pengembalian

    private function seedReturns(): void
    {
        $alfa = $this->makeMember(['name' => 'Anggota Alfa']);
        $beta = $this->makeMember(['name' => 'Anggota Beta']);

        $this->returnRow('Kembali Tepat', '2026-11-18', 0, null, $alfa);
        $this->returnRow('Kembali Telat Lunas', '2026-11-16', 3, 'paid', $beta);
        $this->returnRow('Kembali Telat Utang', '2026-10-30', 2, 'unpaid', $alfa);
        $this->returnRow('Kembali Dibebaskan', '2026-11-17', 1, 'waived', $beta);
    }

    public function test_return_report_has_the_required_columns(): void
    {
        $this->seedReturns();

        $this->actingAs($this->admin)->get('/super-admin/reports/returns')
            ->assertOk()
            ->assertSeeInOrder([
                'No', 'Kode Pengembalian', 'Kode Peminjaman', 'Nama Anggota', 'Judul Buku', 'Tanggal Peminjaman',
                'Tanggal Jatuh Tempo', 'Tanggal Pengembalian', 'Hari Terlambat', 'Total Denda', 'Status Pembayaran Denda', 'Admin yang Memproses',
            ])
            ->assertSee('KMB-')
            ->assertSee('Rp6.000')
            ->assertSee('Lunas')
            ->assertSee($this->staff->name);
    }

    public function test_return_report_period_filters_use_the_return_date(): void
    {
        $this->seedReturns();
        $all = ['Kembali Tepat', 'Kembali Telat Lunas', 'Kembali Telat Utang', 'Kembali Dibebaskan'];

        $this->assertOnlyTitles('/super-admin/reports/returns?period=today', ['Kembali Tepat'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=week', ['Kembali Tepat', 'Kembali Telat Lunas', 'Kembali Dibebaskan'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=month', ['Kembali Tepat', 'Kembali Telat Lunas', 'Kembali Dibebaskan'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all', $all, $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=range&from=2026-10-01&to=2026-10-31', ['Kembali Telat Utang'], $all);
    }

    public function test_return_report_member_book_return_status_and_fine_status_filters(): void
    {
        $this->seedReturns();
        $all = ['Kembali Tepat', 'Kembali Telat Lunas', 'Kembali Telat Utang', 'Kembali Dibebaskan'];

        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&member=Beta', ['Kembali Telat Lunas', 'Kembali Dibebaskan'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&book=Utang', ['Kembali Telat Utang'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&return_status=on_time', ['Kembali Tepat'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&return_status=late', ['Kembali Telat Lunas', 'Kembali Telat Utang', 'Kembali Dibebaskan'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&fine_status=none', ['Kembali Tepat'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&fine_status=unpaid', ['Kembali Telat Utang'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&fine_status=paid', ['Kembali Telat Lunas'], $all);
        $this->assertOnlyTitles('/super-admin/reports/returns?period=all&fine_status=waived', ['Kembali Dibebaskan'], $all);
    }

    public function test_return_report_summary(): void
    {
        $this->seedReturns();

        $this->actingAs($this->admin)->get('/super-admin/reports/returns?period=month')
            ->assertViewHas('summary', fn (array $s) => $s['returns_count'] === 3
                && $s['books'] === 3
                && $s['late_items'] === 2
                && $s['fine_total'] === 8000.0); // 3 hari x 2.000 + 1 hari x 2.000
    }

    // ------------------------------------------------------------------ export Excel

    public function test_excel_export_follows_the_selected_filters(): void
    {
        $this->loan('Buku Terfilter', '2026-11-10', [], $this->makeMember(['name' => 'Anggota Target']));
        $this->loan('Buku Lain', '2026-11-11');

        Excel::fake();

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/excel?member=Target');

        Excel::assertDownloaded('laporan-peminjaman-20261118-1000.xlsx', function (BorrowingExport $export) {
            $rows = $export->query()->get();

            return $rows->count() === 1 && $rows->first()->book_title === 'Buku Terfilter';
        });
    }

    public function test_return_excel_export_is_registered_with_the_return_filters(): void
    {
        $this->seedReturns();

        Excel::fake();

        $this->actingAs($this->admin)->get('/super-admin/reports/returns/export/excel?period=all&fine_status=unpaid');

        Excel::assertDownloaded('laporan-pengembalian-20261118-1000.xlsx', function (ReturnExport $export) {
            return $export->query()->count() === 1;
        });
    }

    public function test_excel_export_produces_a_real_xlsx_file(): void
    {
        $this->loan('Buku Nyata', '2026-11-10');

        $response = $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/excel');

        $response->assertOk()->assertDownload('laporan-peminjaman-20261118-1000.xlsx');
        $this->assertSame('PK', file_get_contents($response->baseResponse->getFile()->getPathname(), false, null, 0, 2));
    }

    public function test_excel_export_is_refused_above_the_row_limit(): void
    {
        $this->loan('Buku Satu', '2026-11-10');
        $this->loan('Buku Dua', '2026-11-11');
        config(['library.report_excel_max_rows' => 1]);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/excel?period=month')
            ->assertRedirect(route('super-admin.reports.borrowings', ['period' => 'month']))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'report.exported']);
    }

    public function test_export_classes_define_headings_numbering_and_safe_cells(): void
    {
        $member = $this->makeMember(['name' => '=HYPERLINK("http://jahat.test","klik")']);
        $this->loan('+Buku Berbahaya', '2026-11-10', [], $member);
        $this->loan('Buku Biasa', '2026-11-11');

        $export = new BorrowingExport(ReportFilters::fromRequest(Request::create('/', 'GET', ['period' => 'all'])));
        $rows = $export->query()->get();

        $this->assertCount(9, $export->headings());
        $this->assertSame('Laporan Peminjaman', $export->title());

        $first = $export->map($rows[1]);   // urutan: terbaru dulu, jadi indeks 1 = peminjaman 10 Nov
        $second = $export->map($rows[0]);

        $this->assertSame([2, 3], [$first[0], $second[0]]); // nomor urut berjalan
        $this->assertSame("'=HYPERLINK(\"http://jahat.test\",\"klik\")", $first[2]);
        $this->assertSame("'+Buku Berbahaya", $first[3]);
        $this->assertSame('Buku Biasa', $second[3]);
        $this->assertSame('10 Nov 2026', $first[5]);

        $this->assertCount(12, (new ReturnExport(ReportFilters::fromRequest(Request::create('/', 'GET'))))->headings());
        $this->assertSame(['J' => '#,##0'], (new ReturnExport(ReportFilters::fromRequest(Request::create('/', 'GET'))))->columnFormats());
    }

    public function test_return_export_maps_numeric_fine_and_status(): void
    {
        $this->returnRow('Buku Denda', '2026-11-16', 3, 'paid');

        $export = new ReturnExport(ReportFilters::fromRequest(Request::create('/', 'GET', ['period' => 'all'])));
        $cells = $export->map($export->query()->get()->first());

        $this->assertSame('Buku Denda', $cells[4]);
        $this->assertSame(3, $cells[8]);
        $this->assertSame(6000.0, $cells[9]);
        $this->assertSame('Lunas', $cells[10]);
        $this->assertSame($this->staff->name, $cells[11]);
    }

    // ------------------------------------------------------------------ export PDF dan cetak

    public function test_pdf_export_downloads_a_pdf_for_both_reports(): void
    {
        $this->loan('Buku PDF', '2026-11-10');
        $this->seedReturns();

        $borrowings = $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/pdf');
        $borrowings->assertOk()->assertDownload('laporan-peminjaman-20261118-1000.pdf');
        $this->assertStringStartsWith('%PDF', $borrowings->getContent());

        $returns = $this->actingAs($this->admin)->get('/super-admin/reports/returns/export/pdf?period=all');
        $returns->assertOk()->assertDownload('laporan-pengembalian-20261118-1000.pdf');
        $this->assertStringStartsWith('%PDF', $returns->getContent());
    }

    public function test_print_view_contains_header_filters_table_totals_and_printer_info(): void
    {
        $this->loan('Buku Cetak', '2026-11-10', [], $this->makeMember(['name' => 'Anggota Cetak']));
        $this->loan('Buku Tidak Ikut', '2026-11-11');

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/print?member=Cetak')
            ->assertOk()
            ->assertSee('window.print()', false)
            ->assertSee('Perpustakaan Uji')
            ->assertSee('Jl. Uji No. 1')
            ->assertSee('LAPORAN PEMINJAMAN')
            ->assertSee('Periode: Bulan ini')
            ->assertSee('Anggota: Cetak')
            ->assertSee('Anggota Cetak')
            ->assertSee('Buku Cetak')
            ->assertDontSee('Buku Tidak Ikut')
            ->assertSee('Total transaksi')
            ->assertSee('Dicetak')
            ->assertSee($this->admin->name);
    }

    public function test_return_print_view_shows_fine_totals(): void
    {
        $this->seedReturns();

        $this->actingAs($this->admin)->get('/super-admin/reports/returns/export/print?period=all')
            ->assertOk()
            ->assertSee('LAPORAN PENGEMBALIAN')
            ->assertSee('Total denda')
            ->assertSee('Rp10.000'); // 6.000 + 4.000 + 2.000 = 12.000, dikurangi tidak ada: semua periode
    }

    public function test_pdf_and_print_are_capped_with_a_clear_notice(): void
    {
        $this->loan('Buku Satu', '2026-11-10');
        $this->loan('Buku Dua', '2026-11-11');
        $this->loan('Buku Tiga', '2026-11-12');
        config(['library.report_pdf_max_rows' => 2]);

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/print')
            ->assertSee('dibatasi 2 baris pertama dari 3 baris')
            ->assertSee('Buku Tiga')
            ->assertSee('Buku Dua')
            ->assertDontSee('Buku Satu');

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/pdf')->assertOk();
    }

    // ------------------------------------------------------------------ audit

    public function test_exports_are_written_to_the_audit_log(): void
    {
        $this->loan('Buku Audit', '2026-11-10');

        $this->actingAs($this->admin)->get('/super-admin/reports/borrowings/export/pdf?period=week&member=Zed');
        $this->actingAs($this->admin)->get('/super-admin/reports/returns/export/excel');

        $this->assertSame(2, ActivityLog::where('action', 'report.exported')->where('user_id', $this->admin->id)->count());

        $log = ActivityLog::where('action', 'report.exported')->oldest('id')->firstOrFail();
        $this->assertSame('pdf', $log->properties['format']);
        $this->assertSame('borrowings', $log->properties['type']);
        $this->assertSame(['period' => 'week', 'member' => 'Zed'], $log->properties['filters']);
    }

    // ------------------------------------------------------------------ filter

    public function test_report_filters_labels_and_week_boundaries(): void
    {
        $make = fn (array $query) => ReportFilters::fromRequest(Request::create('/', 'GET', $query));

        $this->assertSame('Hari ini (18 Nov 2026)', $make(['period' => 'today'])->label());
        $this->assertSame('Minggu ini (16 Nov 2026 s.d. 22 Nov 2026)', $make(['period' => 'week'])->label());
        $this->assertSame('Bulan ini (01 Nov 2026 s.d. 30 Nov 2026)', $make(['period' => 'month'])->label());
        $this->assertSame('Tahun ini (01 Jan 2026 s.d. 31 Des 2026)', $make(['period' => 'year'])->label());
        $this->assertSame('Semua waktu', $make(['period' => 'all'])->label());
        $this->assertSame('Rentang tanggal (sejak 05 Nov 2026)', $make(['period' => 'range', 'from' => '2026-11-05'])->label());
        $this->assertSame('Rentang tanggal (semua tanggal)', $make(['period' => 'range'])->label());
        $this->assertSame('Bulan ini (01 Nov 2026 s.d. 30 Nov 2026)', $make(['period' => 'asal'])->label());

        $this->assertSame(
            ['period' => 'range', 'from' => '2026-11-05', 'to' => '2026-11-10', 'member' => 'Ani'],
            $make(['period' => 'range', 'from' => '2026-11-10', 'to' => '2026-11-05', 'member' => ' Ani '])->toQuery(),
        );
    }
}
