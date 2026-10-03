<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Suspender clases — {{ $group->name }} ({{ ucfirst($group->shift) }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($existing)
                    <div class="mb-4 p-4 bg-amber-100 text-amber-800 rounded-lg text-sm">
                        Ya hay una suspensión registrada para el
                        <strong>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong>
                        con motivo: "{{ $existing->reason }}". Si continuás, se actualizará el motivo y se
                        volverá a marcar a los estudiantes activos como "Suspendida" ese día.
                    </div>
                @endif

                <p class="text-sm text-gray-600 mb-4">
                    Al registrar una suspensión, todos los estudiantes activos del grupo quedarán marcados
                    como <strong>"Suspendida"</strong> ese día en la asistencia (no cuenta como ausencia ni tardía),
                    y luego vas a poder avisarle por WhatsApp a cada encargado el motivo.
                </p>

                <form method="POST" action="{{ route('suspensions.store', $group->id) }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                        <input type="date" name="date" value="{{ old('date', $date) }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm" required>
                        @error('date')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motivo de la suspensión</label>
                        <textarea name="reason" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm"
                                  placeholder="Ej: Fuertes lluvias, actividad institucional, falta de agua potable..."
                                  required>{{ old('reason', $existing->reason ?? '') }}</textarea>
                        @error('reason')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                        {{ $existing ? 'Actualizar motivo' : 'Registrar suspensión y avisar a los encargados' }}
                    </button>
                    <a href="{{ route('attendance.create', $group->id) }}" class="ml-3 text-sm text-gray-500 hover:underline">
                        Volver sin guardar
                    </a>
                </form>

                @if ($existing)
                    <form method="POST" action="{{ route('suspensions.destroy', $group->id) }}" class="mt-4"
                          onsubmit="return confirm('¿Cancelar la suspensión de este día? Se borrará y vas a poder pasar asistencia normalmente.');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button type="submit" class="text-sm text-red-700 hover:underline">
                            Eliminar esta suspensión (fue un error)
                        </button>
                    </form>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
