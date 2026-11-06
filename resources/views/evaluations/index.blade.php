<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $categoryInfo['label'] }}
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
                <form method="GET" action="{{ route('evaluations.index', $category) }}" class="flex flex-wrap items-end gap-4">
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

                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Ver
                    </button>
                </form>
            </div>

            @if ($group)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Valor en la nota -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Valor en la nota</h3>

                        <form method="POST" action="{{ route('evaluations.weight', [$category, $group->id]) }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="subject" value="{{ $subjectId }}">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $categoryInfo['label'] }} en {{ $group->name }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="weight" min="0" max="100" step="0.01"
                                           value="{{ old('weight', $formatNumber($group->{$categoryInfo['weight_column']})) }}"
                                           class="w-24 border-gray-300 rounded-lg text-sm" required>
                                    <span class="text-gray-600">%</span>
                                </div>
                                @error('weight')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                Guardar valor
                            </button>
                        </form>

                        @php $total = $group->totalWeight(); @endphp
                        <p class="text-sm mt-4 {{ $total == 100 ? 'text-gray-500' : 'text-red-600 font-medium' }}">
                            Suma de todos los componentes del grupo: {{ $formatNumber($total) }}%
                            @if ($total != 100)
                                — debería sumar 100%.
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Asistencia {{ $formatNumber($group->attendance_weight) }}%
                            @foreach (\App\Models\Evaluation::CATEGORIES as $key => $info)
                                · {{ $info['label'] }} {{ $formatNumber($group->{$info['weight_column']}) }}%
                            @endforeach
                        </p>
                    </div>

                    <!-- Nueva evaluación -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Nueva evaluación de {{ mb_strtolower($categoryInfo['label']) }}</h3>

                        <form method="POST" action="{{ route('evaluations.store', $category) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="group_id" value="{{ $group->id }}">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" name="name" value="{{ old('name') }}" maxlength="150"
                                       placeholder="Ej. {{ ucfirst($categoryInfo['singular']) }} 1"
                                       class="w-full border-gray-300 rounded-lg text-sm" required>
                                @error('name')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">¿Qué realizaron?</label>
                                <textarea name="description" rows="2" maxlength="2000"
                                          class="w-full border-gray-300 rounded-lg text-sm">{{ old('description') }}</textarea>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                                    <input type="date" name="evaluation_date"
                                           value="{{ old('evaluation_date', now()->format('Y-m-d')) }}"
                                           class="border-gray-300 rounded-lg text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Puntos totales</label>
                                    <input type="number" name="max_points" min="0.01" step="0.01"
                                           value="{{ old('max_points', 100) }}"
                                           class="w-28 border-gray-300 rounded-lg text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subárea</label>
                                    <select name="subject_id" class="border-gray-300 rounded-lg text-sm">
                                        <option value="">Sin subárea</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" @selected(old('subject_id', $subjectId) == $subject->id)>
                                                {{ $subject->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Valor de esta evaluación en la nota</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="weight" min="0" max="100" step="0.01"
                                           value="{{ old('weight') }}" placeholder="Auto"
                                           class="w-24 border-gray-300 rounded-lg text-sm">
                                    <span class="text-gray-600">%</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    Dejalo vacío para repartir el {{ $formatNumber($group->{$categoryInfo['weight_column']}) }}%
                                    de {{ mb_strtolower($categoryInfo['label']) }} en partes iguales (si es una sola, vale todo).
                                    Para dividirlo, poné el valor de cada una: ej. 15% y 20%.
                                </p>
                            </div>

                            @foreach (['evaluation_date', 'max_points', 'weight'] as $field)
                                @error($field)
                                    <p class="text-red-600 text-sm">{{ $message }}</p>
                                @enderror
                            @endforeach

                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                Crear y calificar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Lista de evaluaciones -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">{{ $categoryInfo['label'] }} de {{ $group->name }}</h3>

                    @foreach ($weightWarnings as $warning)
                        <div class="mb-4 p-3 bg-yellow-100 text-yellow-800 rounded-lg text-sm">
                            En el {{ $warning['semester'] }} Semestre {{ $warning['year'] }} los valores asignados suman
                            {{ $formatNumber($warning['assigned']) }}%, más que el
                            {{ $formatNumber($group->{$categoryInfo['weight_column']}) }}% de {{ mb_strtolower($categoryInfo['label']) }}.
                        </div>
                    @endforeach

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Nombre</th>
                                    <th class="px-4 py-2">¿Qué realizaron?</th>
                                    <th class="px-4 py-2">Subárea</th>
                                    <th class="px-4 py-2 text-center">Semestre</th>
                                    <th class="px-4 py-2 text-center">Puntos</th>
                                    <th class="px-4 py-2 text-center">Valor en la nota</th>
                                    <th class="px-4 py-2 text-center">Calificados</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($evaluations as $evaluation)
                                    <tr class="border-b">
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $evaluation->evaluation_date->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-800">{{ $evaluation->name }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ \Illuminate\Support\Str::limit($evaluation->description, 80) ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $evaluation->subject->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-center">{{ [1 => 'I', 2 => 'II'][$evaluation->semester] ?? 'Fuera' }}</td>
                                        <td class="px-4 py-2 text-center">{{ $formatNumber($evaluation->max_points) }}</td>
                                        <td class="px-4 py-2 text-center whitespace-nowrap">
                                            @if (isset($effectiveWeights[$evaluation->id]))
                                                {{ $formatNumber($effectiveWeights[$evaluation->id]) }}%
                                                @if ($evaluation->weight === null)
                                                    <span class="text-xs text-gray-500">(auto)</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center">{{ $evaluation->scores_count }} / {{ $studentsCount }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('evaluations.show', $evaluation) }}"
                                               class="text-indigo-600 hover:underline font-medium">Calificar</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-gray-500">
                                            Todavía no hay evaluaciones de {{ mb_strtolower($categoryInfo['label']) }} para este grupo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    {{ $groups->isEmpty() ? 'No tienes grupos asignados todavía.' : 'Escogé un grupo y dale al botón para ver la información.' }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
