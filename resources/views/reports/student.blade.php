<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Reporte de {{ $student->full_name }}
            </h2>
            <a href="{{ route('reports.index', ['group' => $student->group_id]) }}"
               class="text-sm text-gray-500 hover:underline">
                &larr; Volver a reportes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Filtro de periodo y descarga de PDF -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('reports.student', $student->id) }}"
                      class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                        <select name="period" class="border-gray-300 rounded-lg text-sm">
                            <option value="week" @selected($period === 'week')>Última semana</option>
                            <option value="month" @selected($period === 'month')>Último mes</option>
                            <option value="quarter" @selected($period === 'quarter')>Últimos 3 meses</option>
                            <option value="semester" @selected($period === 'semester')>Últimos 6 meses</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hasta la fecha</label>
                        <input type="date" name="date" value="{{ $date }}" class="border-gray-300 rounded-lg text-sm">
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Ver periodo
                    </button>

                    <a href="{{ route('reports.student.pdf', ['student' => $student->id, 'period' => $period, 'date' => $date]) }}"
                       class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                        Descargar PDF
                    </a>
                </form>

                <p class="text-sm text-gray-500 mt-3">
                    Mostrando del <strong>{{ $startDate->format('d/m/Y') }}</strong>
                    al <strong>{{ $endDate->format('d/m/Y') }}</strong>
                </p>
            </div>

            <!-- Datos del estudiante y del encargado -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Grupo</p>
                        <p class="font-medium text-gray-800">
                            {{ $student->group->name }} ({{ ucfirst($student->group->shift) }})
                        </p>
                        @if ($student->identification)
                            <p class="text-sm text-gray-500 mt-1">Identificación: {{ $student->identification }}</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Encargado</p>
                        @if ($student->guardian_phone)
                            <p class="font-medium text-gray-800">{{ $student->guardian_name ?: 'Sin nombre registrado' }}</p>
                            <p class="text-sm text-gray-500">{{ $student->guardian_phone }} (WhatsApp: {{ $student->whatsapp_phone }})</p>
                        @else
                            <p class="text-sm text-amber-600">Sin teléfono registrado</p>
                        @endif
                        <a href="{{ route('students.edit', $student->id) }}" class="text-xs text-indigo-600 hover:underline">
                            Editar datos del estudiante
                        </a>
                    </div>
                </div>
            </div>

            <!-- Resumen -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center">
                    <p class="text-2xl font-semibold text-green-700">{{ $summary['presente'] }}</p>
                    <p class="text-xs uppercase tracking-wide text-gray-400 mt-1">Presente</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center">
                    <p class="text-2xl font-semibold text-red-700">{{ $summary['ausente'] }}</p>
                    <p class="text-xs uppercase tracking-wide text-gray-400 mt-1">Ausente</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center">
                    <p class="text-2xl font-semibold text-yellow-700">{{ $summary['tardia'] }}</p>
                    <p class="text-xs uppercase tracking-wide text-gray-400 mt-1">Tardía</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-center">
                    <p class="text-2xl font-semibold text-blue-700">{{ $summary['justificada'] }}</p>
                    <p class="text-xs uppercase tracking-wide text-gray-400 mt-1">Justificada</p>
                </div>
            </div>

            <!-- Ausencias y tardías -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Ausencias y tardías del periodo</h3>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Subárea</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2">Observación</th>
                            <th class="px-4 py-2">Avisado por WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($absencesAndLateness as $item)
                            <tr class="border-b">
                                <td class="px-4 py-2">{{ $item->attendance_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $item->subject->name ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $item->status === 'ausente' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $item->notes ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($item->notified_at)
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            Sí, {{ $item->notified_at->format('d/m/Y h:i A') }}
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">
                                            No avisado
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    No tiene ausencias ni tardías en este periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Avisos de WhatsApp enviados -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-1">Avisos de WhatsApp enviados en el periodo</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Muestra cuándo se hizo clic en "Avisar por WhatsApp". No confirma que el mensaje haya sido enviado
                    dentro de WhatsApp, solo que se abrió el chat con el encargado.
                </p>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">Fecha y hora del aviso</th>
                            <th class="px-4 py-2">Motivo</th>
                            <th class="px-4 py-2">Subárea</th>
                            <th class="px-4 py-2">Encargado</th>
                            <th class="px-4 py-2">Teléfono</th>
                            <th class="px-4 py-2">Enviado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notifications as $notification)
                            <tr class="border-b">
                                <td class="px-4 py-2">{{ $notification->sent_at->format('d/m/Y h:i A') }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $notification->status === 'ausente' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ ucfirst($notification->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ $notification->subject->name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $notification->guardian_name ?: 'Sin nombre' }}</td>
                                <td class="px-4 py-2">{{ $notification->guardian_phone }}</td>
                                <td class="px-4 py-2">{{ $notification->teacher->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    No se envió ningún aviso por WhatsApp en este periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Historial completo -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Historial de asistencia del periodo</h3>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Subárea</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $item)
                            @php
                                $colors = [
                                    'presente' => 'bg-green-100 text-green-800',
                                    'ausente' => 'bg-red-100 text-red-800',
                                    'tardia' => 'bg-yellow-100 text-yellow-800',
                                    'justificada' => 'bg-blue-100 text-blue-800',
                                ];
                            @endphp
                            <tr class="border-b">
                                <td class="px-4 py-2">{{ $item->attendance_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $item->subject->name ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $colors[$item->status] }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $item->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                    No hay registros de asistencia en este periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
