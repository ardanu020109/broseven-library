<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #2b2220; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .report-table th { background: #B22234; color: #ffffff; padding: 4px; text-align: left; font-size: 7pt; }
        .report-table td { border-bottom: 1px solid #D9C6B2; padding: 3px 4px; vertical-align: top; font-size: 7pt; }
        .text-center { text-align: center; } .text-end { text-align: right; } .nowrap { white-space: nowrap; }
        .meta td { padding: 1px 0; font-size: 8pt; }
        .summary { width: 100%; margin-top: 8px; background: #F6EDE3; border: 1px solid #D9C6B2; }
        .summary td { padding: 5px 8px; font-size: 8pt; }
        .notice { color: #8B0000; font-size: 7.5pt; margin: 6px 0 0; }
        .footer-sign { width: 100%; margin-top: 14px; }
        .footer-sign td { vertical-align: top; }
        .sign { width: 180px; text-align: center; font-size: 8pt; }
        .muted { color: #6f625c; font-size: 7.5pt; }
    </style>
</head>
<body>
    @include('super-admin.reports._document')
</body>
</html>
