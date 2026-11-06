<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nota de {{ $student->full_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @php
                // 5.00 → "5", 4.50 → "4.5"
                $formatNumber = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
            @endphp

            <a href="{{ route('grades.index', ['group' => $student->group_id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
               class="inline-block mb-4 text-sm text-indigo-600 hover:underline">
                ← Volver a las notas de {{ $student->group->name }}
            </a>

            <!-- Filtros -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('grades.student', $student) }}" class="flex flex-wrap items-end gap-4">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <select name="semester" class="border-gray-300 rounded-lg text-sm">
                            <option value="1" @selected($semester === 1)>I Semestre</option>
                            <option value="2" @selected($semester === 2)>II Semestre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                        <input type="number" name="year" value="{{ $year }}" min="2000" max="2100"
                               class="w-24 border-gray-300 rounded-lg text-sm">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Ver
                    </button>
                    <a href="{{ route('grades.student-pdf', ['student' => $student->id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
                       class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                        Descargar PDF
                    </a>
                </form>
                <p class="text-sm text-gray-500 mt-3">
                    {{ $semester === 1 ? 'I' : 'II' }} Semestre {{ $year }}: del
                    <strong>{{ $startDate->format('d/m/Y') }}</strong> al <strong>{{ $endDate->format('d/m/Y') }}</strong>
                    — Grupo: <strong>{{ $student->group->name }}</strong>
                </p>
            </div>

            <!-- Resumen -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">Componente</th>
                            <th class="px-4 py-2 text-center">Valor</th>
                            <th class="px-4 py-2 text-center">Rendimiento</th>
                            <th class="px-4 py-2 text-center">Obtenido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($row['components'] as $component)
                            <tr class="border-b">
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $component['label'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $formatNumber($component['weight']) }}%</td>
                                <td class="px-4 py-2 text-center">
                                    {{ $component['percentage'] === null ? '—' : $formatNumber($component['percentage']) . '%' }}
                                </td>
                                <td class="px-4 py-2 text-center font-semibold">
                                    {{ $component['points'] === null ? '—' : $formatNumber($component['points']) . '%' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50">
                            <td class="px-4 py-3 font-bold text-gray-900">Total</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $formatNumber($row['evaluated_weight']) }}% evaluado</td>
                            <td></td>
                            <td class="px-4 py-3 text-center font-bold text-gray-900 text-lg">{{ $formatNumber($row['total']) }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Detalle por componente -->
            @foreach ($row['components'] as $key => $component)
                @continue($key === 'asistencia')

                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="font-semibold text-gray-800 mb-4">{{ $component['label'] }}</h3>

                    @if (empty($component['items']))
                        <p class="text-gray-500 text-sm">Sin evaluaciones en este semestre.</p>
                    @else
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Evaluación</th>
                                    <th class="px-4 py-2">¿Qué realizaron?</th>
                                    <th class="px-4 py-2 text-center">Puntos</th>
                                    <th class="px-4 py-2 text-center">%</th>
                                    <th class="px-4 py-2 text-center">Valor</th>
                                    <th class="px-4 py-2 text-center">Obtenido</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($component['items'] as $item)
                                    <tr class="border-b">
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $item['evaluation']->evaluation_date->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-800">{{ $item['evaluation']->name }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $item['evaluation']->description ?: '—' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if ($item['points'] === null)
                                                <span class="text-red-600">Sin nota</span>
                                            @else
                                                {{ $formatNumber($item['points']) }}
                                            @endif
                                            / {{ $formatNumber($item['evaluation']->max_points) }}
                                        </td>
                                        <td class="px-4 py-2 text-center">{{ $formatNumber($item['percentage']) }}%</td>
                                        <td class="px-4 py-2 text-center">{{ $formatNumber($item['weight']) }}%</td>
                                        <td class="px-4 py-2 text-center font-semibold">{{ $formatNumber($item['grade']) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
