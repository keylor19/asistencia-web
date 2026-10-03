<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Asistencia — {{ $group->name }} ({{ ucfirst($group->shift) }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @php
                    $dateFormatted = \Carbon\Carbon::parse($date)->format('d/m/Y');
                @endphp

                @if ($suspension)
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">
                            Las clases de este día ({{ $dateFormatted }}) fueron <strong>suspendidas</strong>.
                            Motivo: {{ $suspension->reason }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <a href="{{ route('suspensions.notify', ['group' => $group->id, 'date' => $date]) }}"
                               class="text-sm text-indigo-600 hover:underline">
                                Ver / avisar a los encargados por WhatsApp
                            </a>
                            <a href="{{ route('suspensions.create', ['group' => $group->id, 'date' => $date]) }}"
                               class="text-sm text-indigo-600 hover:underline">
                                Editar motivo
                            </a>
                            <form method="POST" action="{{ route('suspensions.destroy', ['group' => $group->id]) }}"
                                  onsubmit="return confirm('¿Cancelar la suspensión de este día? Se borrará y vas a poder pasar asistencia normalmente.');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="date" value="{{ $date }}">
                                <button type="submit" class="text-sm text-red-700 hover:underline">
                                    Cancelar suspensión (fue un error)
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mb-4">
                        <a href="{{ route('suspensions.create', ['group' => $group->id, 'date' => $date]) }}"
                           class="inline-flex items-center px-4 py-2 border border-red-300 text-red-700 text-sm font-medium rounded-lg hover:bg-red-50">
                            Suspender clases este día
                        </a>
                    </div>
                @endif

                @if ($subjects->isEmpty())
                    <div class="mb-4 p-4 bg-yellow-100 text-yellow-800 rounded-lg">
                        No hay subáreas registradas todavía.
                        <a href="{{ route('subjects.create') }}" class="underline font-medium">Agrega una aquí</a>
                        antes de pasar lista.
                    </div>
                @else
                    <!-- Selector de fecha y subárea -->
                    <form method="GET" action="{{ route('attendance.create', $group->id) }}"
                          class="mb-6 flex flex-wrap items-end gap-4">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subárea:</label>
                            <select name="subject" id="subject" onchange="this.form.submit()"
                                    class="border-gray-300 rounded-lg shadow-sm text-sm">
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected($subjectId == $subject->id)>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Fecha:</label>
                            <input type="date" name="date" id="date" value="{{ $date }}"
                                   onchange="this.form.submit()"
                                   class="border-gray-300 rounded-lg shadow-sm text-sm">
                        </div>

                        <div>
                            <a href="{{ route('attendance.daily-pdf', ['group' => $group->id, 'date' => $date, 'subject' => $subjectId]) }}"
                               class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                Descargar PDF de este día
                            </a>
                        </div>
                    </form>

                    @php
                        $subjectName = optional($subjects->firstWhere('id', $subjectId))->name ?? 'la clase';
                        $isDiurno = $group->shift === 'diurno';
                    @endphp

                    @unless ($suspension)

                    @if ($isDiurno)
                        <p class="mb-4 text-xs text-gray-500">
                            Este grupo es de jornada diurna: para los estudiantes marcados como <strong>ausente</strong> o
                            <strong>tardía</strong> podés avisarle al encargado por WhatsApp con un clic.
                        </p>
                    @endif

                    <!-- Formulario de asistencia -->
                    <form method="POST" action="{{ route('attendance.store', $group->id) }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="subject_id" value="{{ $subjectId }}">

                        <div class="mb-6">
                            <label for="class_note" class="block text-sm font-medium text-gray-700 mb-1">
                                ¿Qué se trabajó este día?
                            </label>
                            <textarea name="class_note" id="class_note" rows="2"
                                      placeholder="Ej: Se trabajó en instalación de XAMPP y configuración del servidor local"
                                      class="w-full border-gray-300 rounded-lg shadow-sm text-sm">{{ old('class_note', $classNote->content ?? '') }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Se guarda junto con la asistencia y aparece en el cuadro de observaciones del PDF del día.
                            </p>
                        </div>

                        <p class="mb-2 text-xs text-gray-500">
                            Al marcar ausente, tardía o justificada, indicá cuántas lecciones abarca (este grupo es
                            {{ $group->type === 'tecnico' ? 'técnico' : 'académico' }}: día completo = {{ $group->lessons_per_day }} lecciones).
                        </p>

                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">Estudiante</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Lecciones</th>
                                    <th class="px-4 py-3">Observación</th>
                                    @if ($isDiurno)
                                        <th class="px-4 py-3">Avisar</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $current = $existing[$student->id] ?? 'presente';
                                        $currentNote = $existingNotes[$student->id] ?? null;
                                        $currentLessons = $existingLessons[$student->id] ?? null;

                                        $waLinks = null;
                                        if ($isDiurno && $student->whatsapp_phone) {
                                            $waLinks = [
                                                'tardia' => 'https://wa.me/' . $student->whatsapp_phone . '?text=' . rawurlencode(
                                                    "Estimado(a) encargado(a) de {$student->full_name}: le informamos que hoy {$dateFormatted} llegó tarde a la clase de {$subjectName}. - CTP Los Chiles"
                                                ),
                                                'ausente' => 'https://wa.me/' . $student->whatsapp_phone . '?text=' . rawurlencode(
                                                    "Estimado(a) encargado(a) de {$student->full_name}: le informamos que hoy {$dateFormatted} está ausente en la clase de {$subjectName}. - CTP Los Chiles"
                                                ),
                                            ];
                                        }
                                    @endphp
                                    <tr class="border-b" x-data='{ status: "{{ $current }}", lessons: {{ $currentLessons ?? "null" }}, links: @json($waLinks) }'>
                                        <td class="px-4 py-3 font-medium text-gray-800">
                                            {{ $student->full_name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="attendance[{{ $student->id }}]"
                                                    x-model="status"
                                                    class="border-gray-300 rounded-lg text-sm">
                                                <option value="presente">Presente</option>
                                                <option value="ausente">Ausente</option>
                                                <option value="tardia">Tardía</option>
                                                <option value="justificada">Justificada</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="lessons[{{ $student->id }}]"
                                                   x-model="lessons"
                                                   x-show="status !== 'presente'"
                                                   x-bind:disabled="status === 'presente'"
                                                   min="1" max="{{ $group->lessons_per_day }}"
                                                   placeholder="Cant."
                                                   class="w-20 border-gray-300 rounded-lg text-sm">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" name="notes[{{ $student->id }}]"
                                                   value="{{ old('notes.' . $student->id, $currentNote) }}"
                                                   x-show="status !== 'presente'"
                                                   x-bind:placeholder="
                                                       status === 'tardia' ? 'Motivo del atraso' :
                                                       status === 'ausente' ? 'Motivo de la ausencia (o pendiente de justificar)' :
                                                       status === 'justificada' ? 'Fecha y motivo que justifica' : ''
                                                   "
                                                   class="border-gray-300 rounded-lg text-sm w-full">
                                        </td>
                                        @if ($isDiurno)
                                            <td class="px-4 py-3">
                                                <template x-if="links && links[status]">
                                                    <a x-bind:href="links[status]" target="_blank" rel="noopener"
                                                       x-on:click="fetch('{{ route('attendance.notify') }}', {
                                                           method: 'POST',
                                                           headers: {
                                                               'Content-Type': 'application/json',
                                                               'Accept': 'application/json',
                                                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                           },
                                                           body: JSON.stringify({
                                                               student_id: {{ $student->id }},
                                                               group_id: {{ $group->id }},
                                                               subject_id: {{ $subjectId ?? 'null' }},
                                                               date: '{{ $date }}',
                                                               status: status,
                                                           }),
                                                       })"
                                                       class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                                                        WhatsApp
                                                    </a>
                                                </template>
                                                <template x-if="!links && (status === 'ausente' || status === 'tardia')">
                                                    <span class="text-xs text-gray-400">Sin teléfono</span>
                                                </template>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-6">
                            <button type="submit"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                Guardar asistencia
                            </button>
                            <a href="{{ route('groups.index') }}" class="ml-3 text-sm text-gray-500 hover:underline">
                                Volver a mis grupos
                            </a>
                        </div>
                    </form>
                    @endunless
                @endif
            </div>

        </div>
    </div>
</x-app-layout>