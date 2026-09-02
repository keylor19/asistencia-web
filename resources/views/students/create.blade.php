<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Agregar estudiante
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('students.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm" required>
                        @error('full_name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identificación (opcional)</label>
                        <input type="text" name="identification" value="{{ old('identification') }}"
                               class="w-full border-gray-300 rounded-lg shadow-sm">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Grupo</label>
                        <select name="group_id" class="w-full border-gray-300 rounded-lg shadow-sm" required>
                            <option value="">Selecciona un grupo</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>
                                    {{ $group->name }} ({{ ucfirst($group->shift) }})
                                </option>
                            @endforeach
                        </select>
                        @error('group_id')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Guardar
                    </button>
                    <a href="{{ route('students.index') }}" class="ml-3 text-sm text-gray-500 hover:underline">
                        Cancelar
                    </a>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>