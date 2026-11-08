<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Notas del semestre
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @php
                // 5.00 → "5", 4.50 → "4.5"
                $formatNumber = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
                $componentKeys = array_merge(['asistencia'], array_keys(\App\Models\Evaluation::CATEGORIES));
            @endphp

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filtros -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('grades.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                        <select name="group" class="border-gray-300 rounded-lg text-sm" required>
                            <option value="" @selected(! $groupId) disabled>Escoger grupo</option>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <select name="semester" class="border-gray-300 rounded-lg text-sm">
                            <option value="1" @selected($semester === 1)>I Semestre</option>
                            <option value="2" @selected($semester === 2)>II Semestre</option>
                            <option value="anual" @selected($semester === 'anual')>Promedio anual</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                        <input type="number" name="year" value="{{ $year }}" min="2000" max="2100"
                               class="w-24 border-gray-300 rounded-lg text-sm">
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Ver notas
                    </button>

                    @if ($group)
                        <a href="{{ route('grades.export', ['group' => $group->id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
                           class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                            Descargar Excel
                        </a>
                        <a href="{{ route('grades.group-pdf', ['group' => $group->id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
                           class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                            PDF de todos los estudiantes
                        </a>
                    @endif
                </form>
                @if ($group)
                    <p class="text-xs text-gray-500 mt-2">
                        El Excel trae una hoja por subárea y el PDF una página por estudiante con su
                        {{ $semester === 'anual' ? 'promedio anual' : 'desglose' }} por subárea
                        ({{ $subjectId ? 'solo la escogida' : 'las que tienen evaluaciones o listas en el ' . ($semester === 'anual' ? 'año' : 'semestre') }}).
                    </p>
                @endif

                <p class="text-sm text-gray-500 mt-3">
                    {{ ['anual' => 'Año lectivo', 1 => 'I Semestre', 2 => 'II Semestre'][$semester] }} {{ $year }}: del
                    <strong>{{ $startDate->format('d/m/Y') }}</strong> al <strong>{{ $endDate->format('d/m/Y') }}</strong>
                    — Grupo: <strong>{{ $group->name ?? '—' }}</strong>
                    — Subárea: <strong>{{ $subjectId ? optional($subjects->find($subjectId))->name : 'Todas' }}</strong>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Las fechas de los semestres se ajustan en Reportes de asistencia → "Fechas de los semestres".
                </p>
            </div>

            @if ($group)
                @if ($group->totalWeight() != 100)
                    <div class="mb-4 p-4 bg-yellow-100 text-yellow-800 rounded-lg text-sm">
                        Los valores de los componentes de {{ $group->name }} suman {{ $formatNumber($group->totalWeight()) }}%,
                        no 100%. Ajustalos en cada pestaña (Pruebas, Trabajo cotidiano, Proyectos, Tareas) o en
                        Reportes de asistencia.
                    </div>
                @endif

                @if ($semester === 'anual')
                    @include('grades._annual-table')
                @else
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-2">Estudiante</th>
                                    @foreach ($componentKeys as $key)
                                        @php
                                            $column = $key === 'asistencia'
                                                ? 'attendance_weight'
                                                : \App\Models\Evaluation::CATEGORIES[$key]['weight_column'];
                                        @endphp
                                        <th class="px-4 py-2 text-center">
                                            {{ $key === 'asistencia' ? 'Asistencia' : \App\Models\Evaluation::CATEGORIES[$key]['label'] }}
                                            <span class="block font-normal normal-case">({{ $formatNumber($group->{$column}) }}%)</span>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-2 text-center">Total</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr class="border-b">
                                        <td class="px-4 py-2 font-medium">
                                            <a href="{{ route('grades.student', ['student' => $row['student']->id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
                                               class="text-indigo-600 hover:underline">
                                                {{ $row['student']->full_name }}
                                            </a>
                                        </td>
                                        @foreach ($componentKeys as $key)
                                            @php $component = $row['components'][$key]; @endphp
                                            <td class="px-4 py-2 text-center">
                                                @if ($component['points'] === null)
                                                    <span class="text-gray-400">—</span>
                                                @else
                                                    {{ $formatNumber($component['points']) }}%
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-2 text-center">
                                            <span class="font-bold text-gray-900">{{ $formatNumber($row['total']) }}%</span>
                                            @if ($row['evaluated_weight'] < $group->totalWeight())
                                                <span class="block text-xs text-gray-500">de {{ $formatNumber($row['evaluated_weight']) }}% evaluado</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('grades.student-pdf', ['student' => $row['student']->id, 'subject' => $subjectId, 'semester' => $semester, 'year' => $year]) }}"
                                               class="text-red-600 hover:underline font-medium">PDF</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($componentKeys) + 3 }}" class="px-4 py-6 text-center text-gray-500">
                                            Este grupo no tiene estudiantes activos.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <p class="text-xs text-gray-500 mt-4">
                        Cada columna muestra la parte que obtiene el estudiante del valor del componente (por ejemplo,
                        80% en pruebas con un valor de 35% = 28%). Cada evaluación aporta según su valor en la nota;
                        las que no tienen valor propio se reparten en partes iguales lo que sobra del componente. "—" significa que todavía no hay evaluaciones o listas en ese componente durante el
                        semestre. Hacé clic en un estudiante para ver el detalle.
                    </p>
                </div>
                @endif
            @else
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    {{ $groups->isEmpty() ? 'No tienes grupos asignados todavía.' : 'Escogé un grupo y dale al botón para ver la información.' }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
