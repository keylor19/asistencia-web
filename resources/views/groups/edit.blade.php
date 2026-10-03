<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Configurar grupo — {{ $group->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('groups.update', $group->id) }}"
                      x-data="{ type: '{{ old('type', $group->type) }}', lessons_per_day: {{ old('lessons_per_day', $group->lessons_per_day) }} }">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de grupo</label>
                        <select name="type" x-model="type"
                                x-on:change="lessons_per_day = (type === 'tecnico' ? 8 : 12)"
                                class="w-full border-gray-300 rounded-lg shadow-sm">
                            <option value="tecnico">Técnico</option>
                            <option value="academico">Académico</option>
                        </select>
                        @error('type')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Lecciones en un día completo
                        </label>
                        <input type="number" name="lessons_per_day" x-model="lessons_per_day" min="1" max="20"
                               class="w-full border-gray-300 rounded-lg shadow-sm" required>
                        <p class="text-xs text-gray-500 mt-1">
                            Técnico: normalmente 8 lecciones por día. Académico: normalmente 12. Podés ajustarlo si
                            el horario de este grupo es distinto. Este número se usa como tope al registrar cuántas
                            lecciones perdió un estudiante por ausencia o tardía.
                        </p>
                        @error('lessons_per_day')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Guardar
                    </button>
                    <a href="{{ route('groups.index') }}" class="ml-3 text-sm text-gray-500 hover:underline">
                        Cancelar
                    </a>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
