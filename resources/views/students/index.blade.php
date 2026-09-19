<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Estudiantes
            </h2>
            <a href="{{ route('students.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                + Agregar estudiante
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filtro por grupo -->
            <form method="GET" action="{{ route('students.index') }}" class="mb-4 flex items-center gap-3">
                <label for="group" class="text-sm font-medium text-gray-700">Filtrar por grupo:</label>
                <select name="group" id="group" onchange="this.form.submit()"
                        class="border-gray-300 rounded-lg shadow-sm text-sm">
                    <option value="">Todos</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($selectedGroup == $group->id)>
                            {{ $group->name }} ({{ ucfirst($group->shift) }})
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Identificación</th>
                            <th class="px-4 py-3">Grupo</th>
                            <th class="px-4 py-3">Encargado</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            <tr class="border-b">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $student->full_name }}</td>
                                <td class="px-4 py-3">{{ $student->identification ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $student->group->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($student->guardian_phone)
                                        <span class="text-gray-700">{{ $student->guardian_name ?: 'Sin nombre' }}</span>
                                        <span class="block text-xs text-gray-400">{{ $student->guardian_phone }}</span>
                                    @else
                                        <span class="text-xs text-amber-600">Sin teléfono</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($student->active)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Activo</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-500 rounded-full">Dado de baja</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 space-x-2">
                                    <a href="{{ route('reports.student', $student->id) }}"
                                       class="text-indigo-600 hover:underline">Ver reporte</a>

                                    <a href="{{ route('students.edit', $student->id) }}"
                                       class="text-indigo-600 hover:underline">Editar</a>

                                    @if ($student->active)
                                        <form action="{{ route('students.destroy', $student->id) }}" method="POST"
                                              class="inline" onsubmit="return confirm('¿Dar de baja a este estudiante?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Dar de baja</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    No hay estudiantes registrados todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>