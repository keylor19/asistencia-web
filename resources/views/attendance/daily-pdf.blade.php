<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Lista de asistencia - {{ $group->name }} - {{ $date }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
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
            margin-top: 18px;
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
        .col-num { width: 24px; text-align: center; }
        .col-status { width: 90px; }
        .summary-table td {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
        }
        .summary-table th { text-align: center; }
        .badge {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 9px;
        }
        .badge-presente { background-color: #d1fae5; color: #065f46; }
        .badge-ausente { background-color: #fee2e2; color: #991b1b; }
        .badge-tardia { background-color: #fef3c7; color: #92400e; }
        .badge-justificada { background-color: #dbeafe; color: #1e40af; }
        .badge-sin_registrar { background-color: #f3f4f6; color: #6b7280; }
        .meta-table td { border: none; padding: 2px 0; font-size: 10px; }
        .meta-label { color: #6b7280; width: 110px; }
        .observations-box {
            border: 1px solid #9ca3af;
            border-radius: 4px;
            padding: 8px 10px;
            min-height: 90px;
        }
        .observations-box .line {
            border-bottom: 1px solid #d1d5db;
            height: 18px;
        }
        .signatures {
            width: 100%;
            margin-top: 30px;
        }
        .signatures td {
            border: none;
            padding-top: 24px;
            font-size: 10px;
            text-align: center;
        }
        .signatures .sig-line {
            border-top: 1px solid #1f2937;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    @php
        $letterheadPath = public_path('images/membrete-institucional.jpg');
        $letterheadData = file_exists($letterheadPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($letterheadPath)) : null;

        $statusLabels = [
            'presente' => 'Presente',
            'ausente' => 'Ausente',
            'tardia' => 'Tardía',
            'justificada' => 'Justificada',
        ];
    @endphp
    @if ($letterheadData)
        <div class="letterhead">
            <img src="{{ $letterheadData }}">
        </div>
    @endif

    <div class="header">
        <h1>Lista de Asistencia Diaria</h1>
        <p>Generado el {{ now()->format('d/m/Y h:i A') }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Grupo</td>
            <td><strong>{{ $group->name }}</strong> ({{ ucfirst($group->shift) }})</td>
            <td class="meta-label">Fecha</td>
            <td><strong>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Subárea</td>
            <td>{{ $subject->name ?? 'No especificada' }}</td>
            <td class="meta-label">Docente</td>
            <td>{{ $teacher->name }}</td>
        </tr>
        <tr>
            <td class="meta-label">Total de estudiantes</td>
            <td colspan="3">{{ $rows->count() }}</td>
        </tr>
    </table>

    <h2>Resumen del día</h2>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Presente</th>
                <th>Ausente</th>
                <th>Tardía</th>
                <th>Justificada</th>
                <th>Sin registrar</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['presente'] }}</td>
                <td>{{ $summary['ausente'] }}</td>
                <td>{{ $summary['tardia'] }}</td>
                <td>{{ $summary['justificada'] }}</td>
                <td>{{ $summary['sin_registrar'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Detalle de asistencia</h2>
    <table>
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th>Estudiante</th>
                <th>Identificación</th>
                <th class="col-status">Estado</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td class="col-num">{{ $i + 1 }}</td>
                    <td>{{ $row->student->full_name }}</td>
                    <td>{{ $row->student->identification ?? '—' }}</td>
                    <td>
                        @if ($row->status)
                            <span class="badge badge-{{ $row->status }}">{{ $statusLabels[$row->status] }}</span>
                        @else
                            <span class="badge badge-sin_registrar">Sin registrar</span>
                        @endif
                    </td>
                    <td>{{ $row->notes ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No hay estudiantes activos en este grupo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Observaciones generales</h2>
    <div class="observations-box">
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
    </div>

    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                <div class="sig-line">Firma del docente</div>
            </td>
            <td style="width: 50%;">
                <div class="sig-line">Sello / Fecha de archivo</div>
            </td>
        </tr>
    </table>

</body>
</html>
