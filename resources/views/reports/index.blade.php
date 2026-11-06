<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Reporte de Asistencia
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            @php
                // 5.00 → "5", 4.50 → "4.5"
                $formatNumber = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
            @endphp

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filtros -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                        <select name="period" class="border-gray-300 rounded-lg text-sm">
                            <option value="day" @selected($period === 'day')>Día</option>
                            <option value="week" @selected($period === 'week')>Semana</option>
                            <option value="month" @selected($period === 'month')>Mes</option>
                            @foreach ([1 => 'I', 2 => 'II'] as $number => $roman)
                                <option value="semester{{ $number }}" @selected($period === "semester{$number}")>
                                    {{ $roman }} Semestre ({{ $semesters[$number][0]->format('d/m') }} – {{ $semesters[$number][1]->format('d/m') }})
                                </option>
                            @endforeach
                            <option value="all" @selected($period === 'all')>Todo el registro</option>
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

            <!-- Fechas de los semestres -->
            <details class="bg-white shadow-sm sm:rounded-lg p-6 mb-6" @if ($errors->hasAny(['semester1_start', 'semester1_end', 'semester2_start', 'semester2_end'])) open @endif>
                <summary class="font-semibold text-gray-800 cursor-pointer">
                    Fechas de los semestres {{ $semesterYear }}
                </summary>

                <form method="POST" action="{{ route('reports.semesters') }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="year" value="{{ $semesterYear }}">
                    <input type="hidden" name="group" value="{{ $groupId }}">
                    <input type="hidden" name="subject" value="{{ $subjectId }}">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                        @foreach ([1 => 'I', 2 => 'II'] as $number => $roman)
                            <div>
                                <p class="text-sm font-medium text-gray-700 mb-2">{{ $roman }} Semestre</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="date" name="semester{{ $number }}_start"
                                           value="{{ old("semester{$number}_start", $semesters[$number][0]->format('Y-m-d')) }}"
                                           class="border-gray-300 rounded-lg text-sm" required>
                                    <span class="text-gray-500 text-sm">al</span>
                                    <input type="date" name="semester{{ $number }}_end"
                                           value="{{ old("semester{$number}_end", $semesters[$number][1]->format('Y-m-d')) }}"
                                           class="border-gray-300 rounded-lg text-sm" required>
                                </div>
                                @error("semester{$number}_start")
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                @error("semester{$number}_end")
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <button type="submit"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                        Guardar fechas
                    </button>
                </form>

                <p class="text-xs text-gray-500 mt-3">
                    Las fechas se guardan por año. Para configurar otro año, cambiá la fecha de referencia del
                    filtro a ese año y dale "Ver reporte".
                </p>
            </details>

            <!-- Porcentaje de asistencia -->
            @if ($group)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Porcentaje de asistencia</h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-xs uppercase text-gray-500">Asistencia del grupo</p>
                            <p class="text-2xl font-bold {{ $groupPercentage !== null && $groupPercentage < $minPercentage ? 'text-red-600' : 'text-green-700' }}">
                                {{ $groupPercentage !== null ? $groupPercentage . '%' : '—' }}
                            </p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-xs uppercase text-gray-500">Mínimo exigido</p>
                            <p class="text-2xl font-bold text-gray-800">{{ $minPercentage }}%</p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-xs uppercase text-gray-500">Valor de la asistencia</p>
                            <p class="text-2xl font-bold text-gray-800">{{ $formatNumber($attendanceWeight) }}%</p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-xs uppercase text-gray-500">Estudiantes en riesgo</p>
                            <p class="text-2xl font-bold {{ $atRiskCount > 0 ? 'text-red-600' : 'text-gray-800' }}">
                                {{ $atRiskCount }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('reports.settings', $group->id) }}" class="flex flex-wrap items-end gap-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="subject" value="{{ $subjectId }}">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <input type="hidden" name="date" value="{{ $date }}">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Porcentaje mínimo de asistencia para {{ $group->name }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min_attendance_percentage" min="1" max="100"
                                       value="{{ old('min_attendance_percentage', $minPercentage) }}"
                                       class="w-24 border-gray-300 rounded-lg text-sm" required>
                                <span class="text-gray-600">%</span>
                            </div>
                            @error('min_attendance_percentage')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Valor de la asistencia en la nota
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="attendance_weight" min="0.01" max="100" step="0.01"
                                       value="{{ old('attendance_weight', $formatNumber($attendanceWeight)) }}"
                                       class="w-24 border-gray-300 rounded-lg text-sm" required>
                                <span class="text-gray-600">%</span>
                            </div>
                            @error('attendance_weight')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                            Guardar configuración
                        </button>
                    </form>

                    <p class="text-xs text-gray-500 mt-3">
                        Se calcula sobre las veces que se pasó lista en el periodo elegido: solo las ausencias
                        y tardías rebajan el porcentaje; las justificadas no rebajan y las clases suspendidas
                        no se cuentan. Cada estudiante obtiene la parte proporcional del valor de la asistencia
                        (por ejemplo, con 90% de asistencia y un valor de 5%, obtiene 4.5%). Elegí
                        "I Semestre" o "II Semestre" en el periodo para ver la nota de cada semestre; el año
                        se toma de la fecha de referencia y las fechas se ajustan en "Fechas de los semestres".
                    </p>
                </div>
            @endif

            @if ($group)
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
                            <th class="px-4 py-2 text-center">Suspendida</th>
                            <th class="px-4 py-2 text-center">Lecciones perdidas</th>
                            <th class="px-4 py-2 text-center">% Asistencia</th>
                            <th class="px-4 py-2 text-center">Nota asistencia ({{ $formatNumber($attendanceWeight) }}%)</th>
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
                                <td class="px-4 py-2 text-center">{{ $row['suspendida'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['lecciones_perdidas'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if ($row['porcentaje'] === null)
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $row['en_riesgo'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $row['porcentaje'] }}%
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center font-semibold text-gray-800">
                                    {{ $row['puntos'] === null ? '—' : $formatNumber($row['puntos']) . '%' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-gray-500">
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
                                    <th class="px-4 py-2">Lecciones</th>
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
                                                    'suspendida' => 'bg-gray-200 text-gray-700',
                                                ];
                                            @endphp
                                            <span class="px-2 py-1 text-xs rounded-full {{ $colors[$item->status] }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $item->lessons ?? '—' }}</td>
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
            @else
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    {{ $groups->isEmpty() ? 'No tienes grupos asignados todavía.' : 'Escogé un grupo y dale "Ver reporte" para ver la asistencia.' }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>