@use('App\Support\ReportFilters')

<form method="GET" action="{{ $action }}" class="row g-2" id="reportFilter">
    <div class="col-md-3">
        <label for="period" class="form-label small fw-semibold mb-1">Periode</label>
        <select id="period" name="period" class="form-select">
            @foreach (ReportFilters::PERIODS as $value => $label)
                <option value="{{ $value }}" @selected($filters->period === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-md-2 range-field">
        <label for="from" class="form-label small fw-semibold mb-1">Dari</label>
        <input type="date" id="from" name="from" class="form-control"
               value="{{ $filters->period === 'range' ? $filters->from?->toDateString() : '' }}">
    </div>
    <div class="col-6 col-md-2 range-field">
        <label for="to" class="form-label small fw-semibold mb-1">Sampai</label>
        <input type="date" id="to" name="to" class="form-control"
               value="{{ $filters->period === 'range' ? $filters->to?->toDateString() : '' }}">
    </div>

    <div class="col-md-3">
        <label for="member" class="form-label small fw-semibold mb-1">Nama anggota</label>
        <input type="search" id="member" name="member" value="{{ $filters->member }}" maxlength="100" class="form-control" placeholder="Nama atau username">
    </div>
    <div class="col-md-3">
        <label for="book" class="form-label small fw-semibold mb-1">Judul buku</label>
        <input type="search" id="book" name="book" value="{{ $filters->book }}" maxlength="100" class="form-control" placeholder="Judul buku">
    </div>

    @if ($type === 'borrowings')
        <div class="col-md-3">
            <label for="status" class="form-label small fw-semibold mb-1">Status transaksi</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua status</option>
                @foreach (ReportFilters::statusOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($filters->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @else
        <div class="col-md-3">
            <label for="return_status" class="form-label small fw-semibold mb-1">Status pengembalian</label>
            <select id="return_status" name="return_status" class="form-select">
                <option value="">Semua</option>
                @foreach (ReportFilters::RETURN_STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($filters->returnStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="fine_status" class="form-label small fw-semibold mb-1">Status pembayaran denda</label>
            <select id="fine_status" name="fine_status" class="form-select">
                <option value="">Semua</option>
                @foreach (ReportFilters::FINE_STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($filters->fineStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-auto d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        <a href="{{ $action }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
