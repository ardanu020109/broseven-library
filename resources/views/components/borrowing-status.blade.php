@props(['borrowing'])

@if ($borrowing->is_overdue)
    <span class="badge badge-status badge-overdue">Terlambat</span>
@else
    <span class="badge badge-status {{ $borrowing->status->badgeClass() }}">{{ $borrowing->status->label() }}</span>
@endif
