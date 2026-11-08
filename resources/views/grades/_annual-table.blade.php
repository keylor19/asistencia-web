{{-- Promedio anual: (I semestre + II semestre) / 2, aprobado con 70 o más. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-6">
    @php
        $approved = $rows->where('status', 'Aprobado')->count();
        $failed = $rows->where('status', 'Aplazado')->count();
        $pending = $rows->whereNull('status')->count();
        // La nota a mano se guarda por subárea; con "Todas" solo si no hay subáreas.
        $subjectChosen = $subjectId || $subjects->isEmpty();
        $anyTypable = $rows->contains(fn ($row) => $row['first']['can_type'] || $row['second']['can_type']);
    @endphp

    <div class="mb-4 p-4 rounded-lg text-sm {{ $subjectChosen ? 'bg-indigo-50 text-indigo-900' : 'bg-yellow-100 text-yellow-800' }}">
        @if ($subjectChosen)
            <strong>Nota a mano:</strong> solo se puede escribir la nota de un semestre cuando el estudiante
            <strong>no tiene ninguna nota en el sistema</strong> en ese semestre (por ejemplo, si ingresó de otro
            colegio o si el semestre no se llevó en el sistema). Escribí la nota final (0 a 100) en la casilla y dale
            <strong>Guardar notas</strong>. En cuanto el estudiante tenga notas en el sistema, se usa la calculada.
        @else
            Para escribir a mano la nota de un semestre, escogé una <strong>subárea</strong> arriba y dale "Ver notas":
            la nota se guarda por subárea.
        @endif
    </div>

    @if ($errors->has('grades.*'))
        <p class="text-red-600 text-sm mb-3">Revisá las notas: deben estar entre 0 y 100.</p>
    @endif

    <div class="flex flex-wrap gap-3 mb-4 text-sm">
        <span class="px-3 py-1 rounded-full bg-green-100 text-green-800 font-medium">Aprobados: {{ $approved }}</span>
        <span class="px-3 py-1 rounded-full bg-red-100 text-red-800 font-medium">Aplazados: {{ $failed }}</span>
        @if ($pending)
            <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-700 font-medium">Pendientes: {{ $pending }}</span>
        @endif
    </div>

    <form method="POST" action="{{ route('grades.manual', $group->id) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="subject" value="{{ $subjectId }}">

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-2">Estudiante</th>
                        <th class="px-4 py-2 text-center">I Semestre</th>
                        <th class="px-4 py-2 text-center">II Semestre</th>
                        <th class="px-4 py-2 text-center">Promedio anual</th>
                        <th class="px-4 py-2 text-center">Condición</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b">
                            <td class="px-4 py-2 font-medium">{{ $row['student']->full_name }}</td>
                            @foreach ([1 => 'first', 2 => 'second'] as $number => $key)
                                @php $semesterRow = $row[$key]; @endphp
                                <td class="px-4 py-2 text-center">
                                    @if ($subjectChosen && $semesterRow['can_type'])
                                        {{-- Sin notas en el sistema este semestre: se puede escribir a mano. --}}
                                        <input type="number" name="grades[{{ $number }}][{{ $row['student']->id }}]" min="0" max="100" step="0.01"
                                               value="{{ old("grades.{$number}.{$row['student']->id}", $semesterRow['manual'] ? $formatNumber($semesterRow['total']) : '') }}"
                                               placeholder="Sin notas"
                                               class="w-24 border-gray-300 rounded-lg text-sm text-center">
                                        @if ($semesterRow['manual'])
                                            <span class="block text-xs text-indigo-600">escrita a mano</span>
                                        @endif
                                    @elseif ($semesterRow['evaluated_weight'] > 0)
                                        <a href="{{ route('grades.student', ['student' => $row['student']->id, 'subject' => $subjectId, 'semester' => $number, 'year' => $year]) }}"
                                           class="text-indigo-600 hover:underline">{{ $formatNumber($semesterRow['total']) }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-center font-bold text-gray-900">
                                {{ $row['average'] === null ? '—' : $formatNumber($row['average']) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($row['status'] === 'Aprobado')
                                    <span class="px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Aprobado</span>
                                @elseif ($row['status'] === 'Aplazado')
                                    <span class="px-2 py-1 rounded-full bg-red-100 text-red-800 text-xs font-semibold">Aplazado</span>
                                @else
                                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('grades.student-pdf', ['student' => $row['student']->id, 'subject' => $subjectId, 'semester' => 'anual', 'year' => $year]) }}"
                                   class="text-red-600 hover:underline font-medium">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                Este grupo no tiene estudiantes activos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subjectChosen && $anyTypable)
            <div class="mt-4 text-right">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Guardar notas
                </button>
            </div>
        @endif
    </form>

    <p class="text-xs text-gray-500 mt-4">
        Promedio anual = (nota del I semestre + nota del II semestre) / 2. Con
        {{ \App\Services\GradeCalculator::PASSING_GRADE }} o más queda <strong>Aprobado</strong>; si no,
        <strong>Aplazado</strong>. Mientras un semestre no tenga nada evaluado, queda <strong>Pendiente</strong>.
        Hacé clic en la nota de un semestre para ver el detalle.
    </p>
</div>
