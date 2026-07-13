<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
        <div class="mb-6">
            <svg class="w-16 h-16 mx-auto text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Unsubscribe from Emails</h1>
        <p class="text-gray-600 mb-6">Are you sure you want to unsubscribe <strong>{{ $email }}</strong> from our mailing list?</p>
        <form action="{{ route('public.unsubscribe.process', $token) }}" method="POST">
            @csrf
            <button type="submit" class="w-full bg-red-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-red-700 transition">
                Yes, Unsubscribe Me
            </button>
        </form>
        <p class="mt-4 text-sm text-gray-500">You will no longer receive emails from us.</p>
    </div>
</body>
</html>
