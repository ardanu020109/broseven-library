@php
    $logoUri = \App\Support\PdfAssets::logoDataUri($libraryProfile->logo);
@endphp
<table style="width:100%; border-collapse:collapse;">
    <tr>
        @if ($logoUri)
            <td style="width:56px; vertical-align:middle;"><img src="{{ $logoUri }}" alt="" style="height:46px;"></td>
        @endif
        <td style="vertical-align:middle;">
            <div style="font-size:15px; font-weight:bold; color:#B22234;">{{ $libraryProfile->name }}</div>
            @if ($libraryProfile->address)<div style="font-size:8.5px;">{{ $libraryProfile->address }}</div>@endif
            <div style="font-size:8.5px;">
                @if ($libraryProfile->phone)Telp. {{ $libraryProfile->phone }}@endif
                @if ($libraryProfile->phone && $libraryProfile->email) &middot; @endif
                @if ($libraryProfile->email){{ $libraryProfile->email }}@endif
            </div>
        </td>
    </tr>
</table>
<div style="border-top:3px solid #B22234; border-bottom:1px solid #D9C6B2; height:2px; margin:6px 0 8px;"></div>
<div style="text-align:center; font-size:12px; font-weight:bold; letter-spacing:1px; color:#8B0000; margin-bottom:8px;">{{ $title }}</div>
