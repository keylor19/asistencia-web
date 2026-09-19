<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar estudiante
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('students.update', $student->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $student->full_name) }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm" required>
                        @error('full_name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identificación (opcional)</label>
                        <input type="text" name="identification"
                               value="{{ old('identification', $student->identification) }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                        <select name="group_id" class="w-full border-gray-300 rounded-lg shadow-sm" required>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('group_id', $student->group_id) == $group->id)>
                                    {{ $group->name }} ({{ ucfirst($group->shift) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4 pt-2 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 mt-3">
                            Contacto del encargado (para notificaciones de WhatsApp)
                        </p>

                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del encargado (opcional)</label>
                        <input type="text" name="guardian_name"
                               value="{{ old('guardian_name', $student->guardian_name) }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm">
                        @error('guardian_name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp del encargado (opcional)</label>
                        <input type="text" name="guardian_phone"
                               value="{{ old('guardian_phone', $student->guardian_phone) }}"
                               placeholder="8888-8888"
                               class="w-full border-gray-300 rounded-lg shadow-sm">
                        <p class="text-xs text-gray-500 mt-1">Sin necesidad de poner 506, se agrega automáticamente.</p>
                        @error('guardian_phone')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="active" class="w-full border-gray-300 rounded-lg shadow-sm" required>
                            <option value="1" @selected($student->active == 1)>Activo</option>
                            <option value="0" @selected($student->active == 0)>Dado de baja</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Actualizar
                    </button>
                    <a href="{{ route('students.index') }}" class="ml-3 text-sm text-gray-500 hover:underline">
                        Cancelar
                    </a>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>