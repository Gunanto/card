<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch {{ $batch->id }} A4 2x5</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 9.5mm 16.9mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            color: #0f172a;
            font-size: 10px;
        }
        .page {
            width: 176.2mm;
        }
        .meta {
            margin-bottom: 4mm;
            font-size: 9px;
        }
        .grid {
            width: 176.2mm;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .card-cell {
            width: 85.6mm;
            height: 54mm;
            padding: 0;
            vertical-align: top;
        }
        .card-cell-gap-x {
            padding-right: 3mm;
        }
        .card-cell-gap-y {
            padding-bottom: 3mm;
        }
        .card {
            width: 85.6mm;
            height: 54mm;
            border: 0.4mm solid #cbd5e1;
            border-radius: 2mm;
            overflow: hidden;
            position: relative;
            background: #f8fafc;
        }
        .card img {
            width: 85.6mm;
            height: 54mm;
            display: block;
        }
        .fallback {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3mm;
            font-size: 9px;
            color: #64748b;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach(array_chunk($cards, 10) as $pageIndex => $chunk)
    <div class="page">
        <div class="meta">
            <strong>{{ $batch->institution?->name }}</strong> -
            Batch #{{ $batch->id }} -
            Template: {{ $batch->template?->name }} -
            Page {{ $pageIndex + 1 }}
        </div>
        <table class="grid">
            @for($row = 0; $row < 5; $row++)
                <tr>
                    @for($col = 0; $col < 2; $col++)
                        @php $index = ($row * 2) + $col; @endphp
                        <td class="card-cell {{ $col === 0 ? 'card-cell-gap-x' : '' }} {{ $row < 4 ? 'card-cell-gap-y' : '' }}">
                            @if(isset($chunk[$index]))
                                <div class="card">
                                    @if(!empty($chunk[$index]['front_image_data_uri']))
                                        <img src="{{ $chunk[$index]['front_image_data_uri'] }}" alt="Card front">
                                    @else
                                        <div class="fallback">
                                            {{ $chunk[$index]['student_name'] }}<br>
                                            {{ $chunk[$index]['student_code'] }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>
    </div>
    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
