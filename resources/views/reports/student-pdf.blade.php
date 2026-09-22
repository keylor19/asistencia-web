<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de asistencia - {{ $student->full_name }}</title>
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
        .summary-table td {
            text-align: center;
            font-size: 14px;
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
        .badge-yes { background-color: #d1fae5; color: #065f46; }
        .badge-no { background-color: #f3f4f6; color: #6b7280; }
        .meta-table td { border: none; padding: 2px 0; font-size: 10px; }
        .meta-label { color: #6b7280; width: 130px; }
        .footer-note {
            margin-top: 10px;
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>

    @php
        $letterheadPath = public_path('images/membrete-institucional.jpg');
        $letterheadData = file_exists($letterheadPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($letterheadPath)) : null;
    @endphp
    @if ($letterheadData)
        <div class="letterhead">
            <img src="{{ $letterheadData }}">
        </div>
    @endif

    <div class="header">
        <h1>Reporte de asistencia por estudiante</h1>
        <p>Generado el {{ now()->format('d/m/Y h:i A') }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Estudiante</td>
            <td><strong>{{ $student->full_name }}</strong></td>
            <td class="meta-label">Grupo</td>
            <td>{{ $student->group->name }} ({{ ucfirst($student->group->shift) }})</td>
        </tr>
        <tr>
            <td class="meta-label">Identificación</td>
            <td>{{ $student->identification ?? '—' }}</td>
            <td class="meta-label">Periodo</td>
            <td>{{ $startDate->format('d/m/Y') }} al {{ $endDate->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Encargado</td>
            <td>{{ $student->guardian_name ?: 'Sin nombre registrado' }}</td>
            <td class="meta-label">Teléfono del encargado</td>
            <td>{{ $student->guardian_phone ?? 'Sin teléfono registrado' }}</td>
        </tr>
    </table>

    <h2>Resumen del periodo</h2>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Presente</th>
                <th>Ausente</th>
                <th>Tardía</th>
                <th>Justificada</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['presente'] }}</td>
                <td>{{ $summary['ausente'] }}</td>
                <td>{{ $summary['tardia'] }}</td>
                <td>{{ $summary['justificada'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Ausencias y tardías del periodo</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Subárea</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Avisado por WhatsApp</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($absencesAndLateness as $item)
                <tr>
                    <td>{{ $item->attendance_date->format('d/m/Y') }}</td>
                    <td>{{ $item->subject->name ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $item->status === 'ausente' ? 'badge-ausente' : 'badge-tardia' }}">
                            {{ ucfirst($item->status) }}
                        </span>
                    </td>
                    <td>{{ $item->notes ?? '—' }}</td>
                    <td>
                        @if ($item->notified_at)
                            <span class="badge badge-yes">Sí, {{ $item->notified_at->format('d/m/Y h:i A') }}</span>
                        @else
                            <span class="badge badge-no">No avisado</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No tiene ausencias ni tardías en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Avisos de WhatsApp enviados en el periodo</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Motivo</th>
                <th>Subárea</th>
                <th>Encargado</th>
                <th>Teléfono</th>
                <th>Enviado por</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notifications as $notification)
                <tr>
                    <td>{{ $notification->sent_at->format('d/m/Y h:i A') }}</td>
                    <td>
                        <span class="badge {{ $notification->status === 'ausente' ? 'badge-ausente' : 'badge-tardia' }}">
                            {{ ucfirst($notification->status) }}
                        </span>
                    </td>
                    <td>{{ $notification->subject->name ?? '—' }}</td>
                    <td>{{ $notification->guardian_name ?: 'Sin nombre' }}</td>
                    <td>{{ $notification->guardian_phone }}</td>
                    <td>{{ $notification->teacher->name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No se envió ningún aviso por WhatsApp en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer-note">
        "Avisado por WhatsApp" indica que el docente hizo clic en el botón de aviso y se abrió el chat con el
        encargado; no confirma que el mensaje haya sido enviado dentro de WhatsApp.
    </p>

    <h2>Historial de asistencia del periodo</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Subárea</th>
                <th>Estado</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendances as $item)
                <tr>
                    <td>{{ $item->attendance_date->format('d/m/Y') }}</td>
                    <td>{{ $item->subject->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $item->status }}">{{ ucfirst($item->status) }}</span>
                    </td>
                    <td>{{ $item->notes ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No hay registros de asistencia en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
