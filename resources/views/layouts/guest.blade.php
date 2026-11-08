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
                background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI3MiIgaGVpZ2h0PSI3MiIgdmlld0JveD0iMCAwIDcyIDcyIj48ZyBmaWxsPSJub25lIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgb3BhY2l0eT0iMC41NSI+PHJlY3QgeD0iMTciIHk9IjEyIiB3aWR0aD0iMzgiIGhlaWdodD0iNTAiIHJ4PSI1Ii8+PHJlY3QgeD0iMjgiIHk9IjciIHdpZHRoPSIxNiIgaGVpZ2h0PSI5IiByeD0iMiIvPjxwYXRoIGQ9Ik0yMyAyNyBsMyAzIDUtNSIvPjxsaW5lIHgxPSIzNSIgeTE9IjI4IiB4Mj0iNDkiIHkyPSIyOCIvPjxwYXRoIGQ9Ik0yMyAzOSBsMyAzIDUtNSIvPjxsaW5lIHgxPSIzNSIgeTE9IjQwIiB4Mj0iNDkiIHkyPSI0MCIvPjxwYXRoIGQ9Ik0yMyA1MSBsMyAzIDUtNSIvPjxsaW5lIHgxPSIzNSIgeTE9IjUyIiB4Mj0iNDUiIHkyPSI1MiIvPjwvZz48L3N2Zz4=");
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
                    Sistema de Control de Evaluación
                </p>
            </div>

            <div class="relative w-full sm:max-w-md px-6 py-6 bg-white shadow-xl overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
