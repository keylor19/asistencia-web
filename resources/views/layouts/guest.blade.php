<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

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
                opacity: 0.16;
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen flex flex-col justify-center items-center overflow-hidden attendance-bg py-10 px-4">
            <div class="absolute inset-0 attendance-pattern pointer-events-none"></div>

            <div class="relative flex flex-col items-center text-center mb-6">
                <div class="bg-white rounded-full p-2 shadow-lg ring-4 ring-white/30">
                    <img src="{{ asset('images/escudo-ctp-los-chiles.jpg') }}"
                         alt="Escudo del Colegio Técnico Profesional Los Chiles"
                         class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover">
                </div>
                <h1 class="mt-4 text-white font-semibold text-lg sm:text-xl leading-tight drop-shadow">
                    Colegio Técnico Profesional Los Chiles
                </h1>
                <p class="text-white/80 text-sm mt-1">
                    Sistema de Control de Asistencia
                </p>
            </div>

            <div class="relative w-full sm:max-w-md px-6 py-6 bg-white shadow-xl overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
