<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Promedio anual {{ $year }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .letterhead {
            width: 100%;
            margin-bottom: 6px;
        }
        .letterhead img {
            width: 100%;
            height: auto;
            display: block;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #14713f;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 14px;
            margin: 0 0 2px 0;
            color: #14713f;
            text-align: center;
        }
        .header p {
            margin: 0;
            font-size: 10px;
            color: #4b5563;
            text-align: center;
        }
        h2 {
            font-size: 13px;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            text-align: left;
            font-size: 11px;
        }
        th {
            background-color: #f3f4f6;
            text-transform: uppercase;
            font-size: 9px;
            color: #4b5563;
        }
        .center { text-align: center; }
        .average { font-weight: bold; }
        .badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-aprobado { background-color: #d1fae5; color: #065f46; }
        .badge-aplazado { background-color: #fee2e2; color: #991b1b; }
        .badge-pendiente { background-color: #e5e7eb; color: #374151; }
        .meta-table td { border: none; padding: 2px 0; font-size: 10px; }
        .meta-label { color: #6b7280; width: 110px; }
        .footer-note {
            margin-top: 10px;
            font-size: 9px;
            color: #6b7280;
        }
        .signatures { margin-top: 40px; }
        .signatures td { border: none; width: 50%; text-align: center; font-size: 10px; padding-top: 0; }
        .signatures span { display: inline-block; width: 80%; border-top: 1px solid #374151; padding-top: 4px; }
    </style>
</head>
<body>

    @php
        // 5.00 → "5", 4.50 → "4.5"
        $formatNumber = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        $letterheadPath = public_path('images/membrete-institucional.jpg');
        $letterheadData = file_exists($letterheadPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($letterheadPath)) : null;
    @endphp

    @foreach ($students as $student)
        <div class="page">
            @if ($letterheadData)
                <div class="letterhead">
                    <img src="{{ $letterheadData }}">
                </div>
            @endif

            <div class="header">
                <h1>Promedio anual {{ $year }}</h1>
                <p>Generado el {{ now()->format('d/m/Y h:i A') }}</p>
            </div>

            <table class="meta-table">
                <tr>
                    <td class="meta-label">Estudiante</td>
                    <td><strong>{{ $student->full_name }}</strong></td>
                    <td class="meta-label">Grupo</td>
                    <td>{{ $group->name }} ({{ ucfirst($group->shift) }})</td>
                </tr>
                <tr>
                    <td class="meta-label">Identificación</td>
                    <td>{{ $student->identification ?? '—' }}</td>
                    <td class="meta-label">Periodo</td>
                    <td>{{ $startDate->format('d/m/Y') }} al {{ $endDate->format('d/m/Y') }}</td>
                </tr>
            </table>

            <h2>Notas por subárea</h2>
            <table>
                <thead>
                    <tr>
                        <th>Subárea</th>
                        <th class="center">I Semestre</th>
                        <th class="center">II Semestre</th>
                        <th class="center">Promedio anual</th>
                        <th class="center">Condición</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($breakdowns[$student->id] ?? [] as $breakdown)
                        @php $row = $breakdown['row']; @endphp
                        <tr>
                            <td>{{ $breakdown['subject']->name ?? 'Todas' }}</td>
                            <td class="center">{{ $row['first']['evaluated_weight'] > 0 ? $formatNumber($row['first']['total']) : '—' }}</td>
                            <td class="center">{{ $row['second']['evaluated_weight'] > 0 ? $formatNumber($row['second']['total']) : '—' }}</td>
                            <td class="center average">{{ $row['average'] === null ? '—' : $formatNumber($row['average']) }}</td>
                            <td class="center">
                                <span class="badge badge-{{ strtolower($row['status'] ?? 'pendiente') }}">{{ $row['status'] ?? 'Pendiente' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="footer-note">
                Promedio anual = (nota del I semestre + nota del II semestre) / 2. Con
                {{ \App\Services\GradeCalculator::PASSING_GRADE }} o más el estudiante queda Aprobado; si no, Aplazado.
                "Pendiente" significa que uno de los semestres todavía no tiene nada evaluado.
            </p>

            <table class="signatures">
                <tr>
                    <td><span>Firma del docente</span></td>
                    <td><span>Firma del encargado</span></td>
                </tr>
            </table>
        </div>
    @endforeach

</body>
</html>
