<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Avisar suspensión — {{ $group->name }} ({{ ucfirst($group->shift) }})
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
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-gray-700">
                        <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                    </p>
                    <p class="text-sm text-gray-700">
                        <strong>Motivo:</strong> {{ $suspension->reason }}
                    </p>
                    <a href="{{ route('suspensions.create', ['group' => $group->id, 'date' => $date]) }}"
                       class="text-sm text-indigo-600 hover:underline">
                        Editar motivo
                    </a>
                </div>

                <p class="mb-4 text-xs text-gray-500">
                    Hacé clic en "WhatsApp" junto a cada encargado para abrir el chat con el mensaje ya escrito.
                    Vos confirmás el envío desde tu WhatsApp. Los que ya avisaste quedan marcados abajo
                    (esto registra que se hizo clic, no que el mensaje se haya enviado dentro de WhatsApp).
                </p>

                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">Estudiante</th>
                            <th class="px-4 py-3">Encargado</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3">Aviso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            @php
                                $waLink = null;
                                if ($student->whatsapp_phone) {
                                    $message = "Estimado(a) encargado(a) de {$student->full_name}: le informamos que las clases del grupo {$group->name} del día " .
                                        \Carbon\Carbon::parse($date)->format('d/m/Y') .
                                        " fueron suspendidas. Motivo: {$suspension->reason}. - CTP Los Chiles";
                                    $waLink = 'https://wa.me/' . $student->whatsapp_phone . '?text=' . rawurlencode($message);
                                }
                                $alreadyNotified = $notifiedStudentIds->contains($student->id);
                            @endphp
                            <tr class="border-b" x-data="{ notified: {{ $alreadyNotified ? 'true' : 'false' }} }">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $student->full_name }}</td>
                                <td class="px-4 py-3">{{ $student->guardian_name ?: 'Sin nombre' }}</td>
                                <td class="px-4 py-3">{{ $student->guardian_phone ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($waLink)
                                        <a href="{{ $waLink }}" target="_blank" rel="noopener"
                                           x-on:click="
                                               notified = true;
                                               fetch('{{ route('attendance.notify') }}', {
                                                   method: 'POST',
                                                   headers: {
                                                       'Content-Type': 'application/json',
                                                       'Accept': 'application/json',
                                                       'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                   },
                                                   body: JSON.stringify({
                                                       student_id: {{ $student->id }},
                                                       group_id: {{ $group->id }},
                                                       date: '{{ $date }}',
                                                       status: 'suspendida',
                                                   }),
                                               })
                                           "
                                           class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                                            WhatsApp
                                        </a>
                                        <span x-show="notified" class="ml-2 text-xs text-green-700">✓ Avisado</span>
                                    @else
                                        <span class="text-xs text-gray-400">Sin teléfono</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                                    No hay estudiantes activos en este grupo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-6">
                    <a href="{{ route('attendance.create', ['group' => $group->id, 'date' => $date]) }}"
                       class="text-sm text-gray-500 hover:underline">
                        &larr; Volver a la asistencia de este día
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
