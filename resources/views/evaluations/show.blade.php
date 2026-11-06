<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $categoryInfo['label'] }} — {{ $evaluation->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @php
                // 5.00 → "5", 4.50 → "4.5"
                $formatNumber = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
            @endphp

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <a href="{{ route('evaluations.index', ['category' => $evaluation->category, 'group' => $evaluation->group_id]) }}"
               class="inline-block mb-4 text-sm text-indigo-600 hover:underline">
                ← Volver a {{ mb_strtolower($categoryInfo['label']) }} de {{ $evaluation->group->name }}
            </a>

            <form method="POST" action="{{ route('evaluations.update', $evaluation) }}"
                  x-data="{
                      maxPoints: {{ (float) old('max_points', $evaluation->max_points) }},
                      weight: @js((string) old('weight', $formatNumber($evaluation->weight))),
                      categoryWeight: {{ (float) $evaluation->group->{$categoryInfo['weight_column']} }},
                      autoWeight: {{ $autoWeight === null ? 'null' : $autoWeight }},
                      // Valor de la evaluación en la nota: el escrito o, si está vacío, el automático.
                      get value() { return this.weight === '' ? this.autoWeight : Number(this.weight); },
                      round(n) { return Math.round(n * 100) / 100; },
                  }">
                @csrf
                @method('PUT')

                <!-- Datos de la evaluación -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <div class="flex-1 min-w-[12rem]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" name="name" value="{{ old('name', $evaluation->name) }}" maxlength="150"
                                   class="w-full border-gray-300 rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                            <input type="date" name="evaluation_date"
                                   value="{{ old('evaluation_date', $evaluation->evaluation_date->format('Y-m-d')) }}"
                                   class="border-gray-300 rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Puntos totales</label>
                            <input type="number" name="max_points" min="0.01" step="0.01" x-model.number="maxPoints"
                                   value="{{ old('max_points', $formatNumber($evaluation->max_points)) }}"
                                   class="w-28 border-gray-300 rounded-lg text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subárea</label>
                            <select name="subject_id" class="border-gray-300 rounded-lg text-sm">
                                <option value="">Sin subárea</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id', $evaluation->subject_id) == $subject->id)>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">¿Qué realizaron?</label>
                        <textarea name="description" rows="2" maxlength="2000"
                                  class="w-full border-gray-300 rounded-lg text-sm">{{ old('description', $evaluation->description) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor de esta evaluación en la nota</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="weight" min="0" max="100" step="0.01" placeholder="Auto"
                                   x-model="weight"
                                   class="w-24 border-gray-300 rounded-lg text-sm">
                            <span class="text-gray-600">%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Vacío = se reparte en partes iguales el
                            {{ $formatNumber($evaluation->group->{$categoryInfo['weight_column']}) }}%
                            de {{ mb_strtolower($categoryInfo['label']) }} con las demás evaluaciones sin valor del semestre.
                        </p>
                        <p class="text-xs text-red-600 font-medium mt-1" x-show="weight !== '' && Number(weight) > categoryWeight" style="display: none">
                            Ojo: es más que el {{ $formatNumber($evaluation->group->{$categoryInfo['weight_column']}) }}%
                            que vale todo {{ mb_strtolower($categoryInfo['label']) }}.
                        </p>
                    </div>

                    @foreach (['name', 'evaluation_date', 'max_points', 'weight', 'subject_id', 'description'] as $field)
                        @error($field)
                            <p class="text-red-600 text-sm">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>

                <!-- Notas de los estudiantes -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Notas de los estudiantes</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Escribí los puntos que obtuvo cada estudiante. Si lo dejás en blanco, en la nota del semestre cuenta como 0.
                    </p>

                    @if ($errors->has('points.*'))
                        <p class="text-red-600 text-sm mb-3">Hay puntos fuera de rango: deben estar entre 0 y los puntos totales.</p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-2">Estudiante</th>
                                    <th class="px-4 py-2 text-center">Puntos</th>
                                    <th class="px-4 py-2 text-center">
                                        <span x-text="value === null ? '%' : 'Obtenido de ' + round(value) + '%'">%</span>
                                    </th>
                                    <th class="px-4 py-2">Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($students as $student)
                                    @php $score = $scores->get($student->id); @endphp
                                    <tr class="border-b" x-data="{ points: '{{ old("points.{$student->id}", $formatNumber($score?->points)) }}' }">
                                        <td class="px-4 py-2 font-medium text-gray-800">{{ $student->full_name }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <input type="number" name="points[{{ $student->id }}]" x-model="points"
                                                   min="0" :max="maxPoints" step="0.01"
                                                   class="w-24 border-gray-300 rounded-lg text-sm text-center">
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-600">
                                            <template x-if="points === '' || !maxPoints">
                                                <span>—</span>
                                            </template>
                                            <template x-if="points !== '' && maxPoints">
                                                <div>
                                                    {{-- Ej. 100/100 en una tarea que vale 15% → 15% --}}
                                                    <span class="font-semibold text-gray-900"
                                                          x-show="value !== null"
                                                          x-text="round(points / maxPoints * value) + '%'"></span>
                                                    <span class="block text-xs text-gray-500"
                                                          x-text="round(points / maxPoints * 100) + '% de la evaluación'"></span>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" name="notes[{{ $student->id }}]" maxlength="255"
                                                   value="{{ old("notes.{$student->id}", $score?->notes) }}"
                                                   class="w-full border-gray-300 rounded-lg text-sm">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                            Este grupo no tiene estudiantes activos.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Guardar notas
                </button>
            </form>

            <form method="POST" action="{{ route('evaluations.destroy', $evaluation) }}" class="mt-6"
                  onsubmit="return confirm('¿Eliminar esta evaluación y todas sus notas?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-600 hover:underline">Eliminar evaluación</button>
            </form>

        </div>
    </div>
</x-app-layout>
