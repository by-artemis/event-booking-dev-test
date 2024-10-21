<x-guest-layout>
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold mb-4">Connect to Google Calendar</h1>
        <p class="mb-4">To book events, please connect your Google Calendar. This allows us to check for conflicts and add events directly to your calendar.</p>
        <a href="{{ route('google.auth') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded">Connect to Google Calendar</a>
    </div>
</x-guest-layout>
