<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('scripts')
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">

        {{-- <div
            class="w-full md:max-w-lg mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg"> --}}
        <main class="flex items-center justify-center flex-col min-h-screen bg-gray-100 py-8 px-4">
            <div class="py-6">
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-4xl w-full">
                {{ $slot }}
            </div>
        </main>
        {{-- </div> --}}
    </div>
    <footer class="bg-blue-500 text-white py-4">
        <div class="container mx-auto text-center">
            <p>&copy; {{ date('Y') }} Event Booking App. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>
