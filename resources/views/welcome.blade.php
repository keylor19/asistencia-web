<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Asistencia CTP Los Chiles') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .attendance-bg {
                background-image:
                    radial-gradient(circle at 15% 20%, rgba(255,255,255,0.10) 0, transparent 40%),
                    radial-gradient(circle at 85% 80%, rgba(255,255,255,0.08) 0, transparent 45%),
                    linear-gradient(135deg, #0f5132 0%, #14713f 35%, #0e6b6e 70%, #0b4f66 100%);
            }
            .attendance-pattern {
                background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc3MicgaGVpZ2h0PSc3Micgdmlld0JveD0nMCAwIDcyIDcyJz48ZyBmaWxsPSdub25lJyBzdHJva2U9J3doaXRlJyBzdHJva2Utd2lkdGg9JzInIG9wYWNpdHk9JzAuNTUnPjxyZWN0IHg9JzEyJyB5PScxNicgd2lkdGg9JzQ4JyBoZWlnaHQ9JzQwJyByeD0nNScvPjxsaW5lIHgxPScxMicgeTE9JzI3JyB4Mj0nNjAnIHkyPScyNycvPjxsaW5lIHgxPScyMycgeTE9JzknIHgyPScyMycgeTI9JzIwJy8+PGxpbmUgeDE9JzQ5JyB5MT0nOScgeDI9JzQ5JyB5Mj0nMjAnLz48cGF0aCBkPSdNMjcgNDEgTDM0IDQ4IEw0OSAzMycgc3Ryb2tlLXdpZHRoPSczJyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnLz48L2c+PC9zdmc+Cg==");
                background-size: 72px 72px;
                opacity: 0.14;
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen attendance-bg overflow-hidden">
            <div class="absolute inset-0 attendance-pattern pointer-events-none"></div>

            <div class="relative flex flex-col min-h-screen">
                <!-- Header -->
                <header class="w-full max-w-6xl mx-auto px-6 py-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white rounded-full p-1 shadow-md ring-2 ring-white/30">
                            <img src="{{ asset('images/escudo-ctp-los-chiles.jpg') }}"
                                 alt="Escudo del Colegio Técnico Profesional Los Chiles"
                                 class="w-12 h-12 rounded-full object-cover">
                        </div>
                        <div class="text-white leading-tight">
                            <p class="font-semibold text-sm sm:text-base">CTP Los Chiles</p>
                            <p class="text-white/70 text-xs">Sistema de Control de Asistencia</p>
                        </div>
                    </div>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}"
                                   class="inline-block px-5 py-2 bg-white text-emerald-800 font-medium rounded-md text-sm shadow hover:bg-emerald-50 transition">
                                    Ir al panel
                                </a>
                            @else
                                <a href="{{ route('login') }}"
                                   class="inline-block px-5 py-2 text-white border border-white/40 hover:bg-white/10 rounded-md text-sm transition">
                                    Iniciar sesión
                                </a>
                            @endauth
                        </nav>
                    @endif
                </header>

                <!-- Hero -->
                <main class="flex-1 flex items-center">
                    <div class="w-full max-w-6xl mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                        <div class="text-white text-center lg:text-left">
                            <span class="inline-block px-3 py-1 rounded-full bg-white/10 text-white/90 text-xs font-medium tracking-wide uppercase mb-4">
                                Colegio Técnico Profesional Los Chiles
                            </span>
                            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight mb-4">
                                Sistema de Control de Asistencia para Docentes
                            </h1>
                            <p class="text-white/85 text-base sm:text-lg mb-8 max-w-xl mx-auto lg:mx-0">
                                Registra, consulta y gestiona la asistencia de tus estudiantes de forma rápida,
                                ordenada y desde un solo lugar.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center justify-center px-6 py-3 bg-white text-emerald-800 font-semibold rounded-lg shadow hover:bg-emerald-50 transition">
                                    Iniciar sesión
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-white/95 rounded-xl shadow-lg p-5">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                                </div>
                                <h3 class="font-semibold text-gray-800 mb-1">Registro diario</h3>
                                <p class="text-sm text-gray-600">Marca la asistencia de cada grupo en segundos.</p>
                            </div>
                            <div class="bg-white/95 rounded-xl shadow-lg p-5">
                                <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
                                </div>
                                <h3 class="font-semibold text-gray-800 mb-1">Reportes claros</h3>
                                <p class="text-sm text-gray-600">Consulta el historial y las estadísticas por estudiante o grupo.</p>
                            </div>
                            <div class="bg-white/95 rounded-xl shadow-lg p-5">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <h3 class="font-semibold text-gray-800 mb-1">Grupos y materias</h3>
                                <p class="text-sm text-gray-600">Organiza tus secciones y asignaturas fácilmente.</p>
                            </div>
                            <div class="bg-white/95 rounded-xl shadow-lg p-5">
                                <div class="w-10 h-10 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <h3 class="font-semibold text-gray-800 mb-1">Acceso seguro</h3>
                                <p class="text-sm text-gray-600">Solo el personal docente autorizado puede ingresar.</p>
                            </div>
                        </div>
                    </div>
                </main>

                <footer class="text-center text-white/60 text-xs py-6">
                    &copy; {{ date('Y') }} Colegio Técnico Profesional Los Chiles &mdash; Sistema de Control de Asistencia
                </footer>
            </div>
        </div>
    </body>
</html>
