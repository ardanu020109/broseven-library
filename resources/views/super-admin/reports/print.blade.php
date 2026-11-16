<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>{{ $pageTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #2b2220; margin: 0; padding: 12px; }
        .toolbar { margin-bottom: 12px; display: flex; gap: 8px; }
        .toolbar button { padding: 6px 14px; border: 0; border-radius: 6px; background: #B22234; color: #fff; cursor: pointer; font-size: 13px; }
        .toolbar button.secondary { background: #6f625c; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .report-table th { background: #B22234; color: #fff; padding: 5px; text-align: left; font-size: 10px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .report-table td { border-bottom: 1px solid #D9C6B2; padding: 4px 5px; vertical-align: top; }
        .report-table tr { break-inside: avoid; }
        .text-center { text-align: center; } .text-end { text-align: right; } .nowrap { white-space: nowrap; }
        .meta td { padding: 1px 0; }
        .summary { width: 100%; margin-top: 10px; background: #F6EDE3; border: 1px solid #D9C6B2; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .summary td { padding: 6px 10px; }
        .notice { color: #8B0000; margin: 8px 0 0; }
        .footer-sign { width: 100%; margin-top: 18px; break-inside: avoid; }
        .footer-sign td { vertical-align: top; }
        .sign { width: 220px; text-align: center; }
        .muted { color: #6f625c; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak</button>
        <button type="button" class="secondary" onclick="window.close()">Tutup</button>
    </div>

    @include('super-admin.reports._document')

    <script>
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
    </script>
</body>
</html>
