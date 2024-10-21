<x-guest-layout>
    @if (session('success'))
        <div class="flex items-center p-4 mb-4 text-sm text-green-800 border border-green-300 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 dark:border-green-800"
            role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="container mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold mb-6">Event List</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($events as $event)
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold mb-2">{{ $event->name }}</h2>
                    <p class="text-gray-700 mb-2"><strong>Duration:</strong> {{ $event->duration }} minutes</p>
                    <p class="text-gray-600 mb-4">{{ $event->description }}</p>
                    <a href="{{ route('bookings.create', $event->id) }}" class="text-blue-500 hover:underline">View
                        Details</a>
                </div>
            @endforeach
        </div>
    </div>
</x-guest-layout>