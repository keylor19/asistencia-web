<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mis Grupos
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse ($groups as $group)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800">{{ $group->name }}</h3>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                {{ $group->shift === 'diurno' ? 'bg-yellow-100 text-yellow-800' : 'bg-indigo-100 text-indigo-800' }}">
                                {{ ucfirst($group->shift) }}
                            </span>
                        </div>

                        <p class="text-gray-500 text-sm mb-4">
                            {{ $group->students_count }} estudiante(s)
                        </p>

                        <a href="{{ route('attendance.create', ['group' => $group->id]) }}"
                           class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            Pasar lista
                        </a>
                    </div>
                @empty
                    <p class="text-gray-500">No tienes grupos asignados todavía.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>