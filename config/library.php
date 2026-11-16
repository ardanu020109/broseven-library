<?php

return [
    /*
    | Batas baris laporan agar server tidak kehabisan memori.
    | Excel dibaca bertahap sehingga batasnya lebih tinggi; PDF dan cetak dibatasi ke baris pertama saja.
    */
    'report_excel_max_rows' => 20000,
    'report_pdf_max_rows' => 500,

    /*
    | Content-Security-Policy untuk halaman HTML. Matikan (CSP_ENABLED=false) hanya untuk diagnosis.
    */
    'csp_enabled' => env('CSP_ENABLED', true),
];
