<x-guest-layout>
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold mb-6">Your Bookings</h1>

        @if ($bookedEvents->isEmpty())
            <p class="text-gray-600">You have no bookings yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Event Name</th>
                            <th class="py-2 px-4 border-b text-left">Date</th>
                            <th class="py-2 px-4 border-b text-left">Time</th>
                            <th class="py-2 px-4 border-b text-left">Email</th>
                            <th class="py-2 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookedEvents as $booking)
                            <tr>
                                <td class="py-2 px-4 border-b">{{ $booking->event->name }}</td>
                                <td class="py-2 px-4 border-b">
                                    {{ \Carbon\Carbon::parse($booking->booking_date)->format('m-d-Y') }}
                                </td>
                                <td class="py-2 px-4 border-b">
                                    {{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }}
                                </td>
                                <td class="py-2 px-4 border-b">{{ $booking->attendee_email }}</td>
                                <td class="py-2 px-4 border-b flex flex-row">
                                    <a href="{{ route('bookings.create', $booking->event->id) }}"
                                        class="pe-3 font-normal text-blue-600 dark:text-blue-500 hover:underline">
                                        <!-- Select Another Time Slot -->
                                        <div class="flex w-6 h-6 fill-current text-gray-500" >
                                            <x-icon-add/>
                                        </div>
                                    </a>
                                    <form action="{{ route('bookings.destroy', $booking->id) }}" 
                                        method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this booking?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">
                                            <div class="flex w-6 h-6 fill-current text-gray-500" >
                                                <!-- Delete -->
                                                <x-icon-delete/>
                                            </div>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="my-6">
            <a href="/" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">
                Back to Events
            </a>
        </div>
    </div>
</x-guest-layout>