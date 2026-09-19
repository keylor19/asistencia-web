<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Reporte de Asistencia
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            <!-- Filtros -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                        <select name="group" class="border-gray-300 rounded-lg text-sm">
                            @foreach ($groups as $g)
                                <option value="{{ $g->id }}" @selected($groupId == $g->id)>
                                    {{ $g->name }} ({{ ucfirst($g->shift) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subárea</label>
                        <select name="subject" class="border-gray-300 rounded-lg text-sm">
                            <option value="">Todas las subáreas</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected($subjectId == $subject->id)>
                                    {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                        <select name="period" class="border-gray-300 rounded-lg text-sm">
                            <option value="day" @selected($period === 'day')>Día</option>
                            <option value="week" @selected($period === 'week')>Semana</option>
                            <option value="month" @selected($period === 'month')>Mes</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de referencia</label>
                        <input type="date" name="date" value="{{ $date }}"
                               class="border-gray-300 rounded-lg text-sm">
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Ver reporte
                    </button>
                </form>

                <p class="text-sm text-gray-500 mt-3">
                    Mostrando del <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</strong>
                    al <strong>{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
                    — Grupo: <strong>{{ $group->name ?? '—' }}</strong>
                    — Subárea: <strong>{{ $subjectId ? $subjects->find($subjectId)->name : 'Todas' }}</strong>
                </p>
            </div>

            <!-- Resumen por estudiante -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-gray-800 mb-4">Resumen por estudiante</h3>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">Estudiante</th>
                            <th class="px-4 py-2 text-center">Presente</th>
                            <th class="px-4 py-2 text-center">Ausente</th>
                            <th class="px-4 py-2 text-center">Tardía</th>
                            <th class="px-4 py-2 text-center">Justificada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr class="border-b">
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    <a href="{{ route('reports.student', $row['student_id']) }}" class="text-indigo-600 hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-center">{{ $row['presente'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['ausente'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['tardia'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['justificada'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    No hay registros de asistencia en este periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Detalle día por día con observaciones -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Detalle y observaciones</h3>

                @forelse ($records as $studentId => $items)
                    <div class="mb-6">
                        <p class="font-medium text-gray-800 mb-2">{{ $items->first()->student->full_name }}</p>
                        <table class="w-full text-sm text-left mb-2">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Subárea</th>
                                    <th class="px-4 py-2">Estado</th>
                                    <th class="px-4 py-2">Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr class="border-b">
                                        <td class="px-4 py-2">{{ $item->attendance_date->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2">{{ $item->subject->name ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            @php
                                                $colors = [
                                                    'presente' => 'bg-green-100 text-green-800',
                                                    'ausente' => 'bg-red-100 text-red-800',
                                                    'tardia' => 'bg-yellow-100 text-yellow-800',
                                                    'justificada' => 'bg-blue-100 text-blue-800',
                                                ];
                                            @endphp
                                            <span class="px-2 py-1 text-xs rounded-full {{ $colors[$item->status] }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $item->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="text-gray-500">No hay registros para mostrar.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>