<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Notas {{ $semester === 1 ? 'I' : 'II' }} Semestre {{ $year }}</title>
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
            padding: 4px 6px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f3f4f6;
            text-transform: uppercase;
            font-size: 9px;
            color: #4b5563;
        }
        .center { text-align: center; }
        .component td { background-color: #f9fafb; font-weight: bold; }
        .item td:first-child { padding-left: 18px; }
        .total td { background-color: #e8f5ee; font-weight: bold; font-size: 11px; }
        .muted { color: #9ca3af; }
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
        // Mismo orden que el Excel de notas.
        $componentKeys = ['cotidiano', 'tareas', 'pruebas', 'proyectos', 'asistencia'];
        $semesterLabel = ($semester === 1 ? 'I' : 'II') . ' Semestre ' . $year;

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
                <h1>Desglose de notas — {{ $semesterLabel }}</h1>
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

            @foreach ($breakdowns[$student->id] ?? [] as $breakdown)
                @php $row = $breakdown['row']; @endphp

                <h2>{{ $breakdown['subject']->name ?? 'Notas' }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Componente / evaluación</th>
                            <th class="center">Fecha</th>
                            <th class="center">Puntos</th>
                            <th class="center">% logrado</th>
                            <th class="center">Valor</th>
                            <th class="center">Obtenido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($componentKeys as $key)
                            @php $component = $row['components'][$key]; @endphp
                            <tr class="component">
                                <td>{{ $component['label'] }}</td>
                                <td></td>
                                <td></td>
                                <td class="center">
                                    @if ($component['percentage'] !== null)
                                        {{ $formatNumber($component['percentage']) }}%
                                    @endif
                                </td>
                                <td class="center">{{ $formatNumber($component['weight']) }}%</td>
                                <td class="center">
                                    @if ($component['points'] === null)
                                        <span class="muted">Sin evaluar</span>
                                    @else
                                        {{ $formatNumber($component['points']) }}%
                                    @endif
                                </td>
                            </tr>
                            @foreach ($component['items'] as $item)
                                <tr class="item">
                                    <td>{{ $item['evaluation']->name }}</td>
                                    <td class="center">{{ $item['evaluation']->evaluation_date->format('d/m/Y') }}</td>
                                    <td class="center">
                                        {{ $item['points'] === null ? 'Sin nota' : $formatNumber($item['points']) }}
                                        / {{ $formatNumber($item['evaluation']->max_points) }}
                                    </td>
                                    <td class="center">{{ $formatNumber($item['percentage']) }}%</td>
                                    <td class="center">{{ $formatNumber($item['weight']) }}%</td>
                                    <td class="center">{{ $formatNumber($item['grade']) }}%</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr class="total">
                            <td colspan="4">Nota de {{ $breakdown['subject']->name ?? 'el semestre' }}</td>
                            <td class="center">{{ $formatNumber($group->totalWeight()) }}%</td>
                            <td class="center">{{ $formatNumber($row['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
                @if ($row['evaluated_weight'] < $group->totalWeight())
                    <p class="footer-note">
                        Hasta ahora se ha evaluado {{ $formatNumber($row['evaluated_weight']) }}% de {{ $formatNumber($group->totalWeight()) }}%.
                    </p>
                @endif
            @endforeach

            <p class="footer-note">
                "% logrado" es lo que obtuvo el estudiante en cada evaluación; "Obtenido" es la parte de su valor en la nota.
                En asistencia, las ausencias justificadas no rebajan. Una evaluación sin nota cuenta como 0.
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
