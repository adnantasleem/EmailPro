<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{'EmailPro'}}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @if(session()->has('impersonated_by'))
                <div class="bg-red-600 text-white px-4 py-2 text-center text-sm font-bold flex justify-center items-center">
                    <span>You are currently impersonating {{ auth()->user()->name }}.</span>
                    <form method="POST" action="{{ route('impersonate.leave') }}" class="ml-4 inline">
                        @csrf
                        <button type="submit" class="bg-white text-red-600 hover:bg-gray-100 px-3 py-1 rounded text-xs">
                            Leave Impersonation
                        </button>
                    </form>
                </div>
            @endif
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
