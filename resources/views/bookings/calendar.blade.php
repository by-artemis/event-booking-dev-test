<x-guest-layout>
    @push('scripts')
        @vite('resources/js/custom.js')
    @endpush
    <div class="container mx-auto py-8">
        @if (!request('submit_schedule'))
            <h1 class="text-2xl font-bold mb-6">Select a Time Slot for <b class="px-1.5 py-1 bg-yellow-100">{{ $event->name }}</b></h1>
            
            @if ($errors->any())
                <div class="flex items-center p-4 mb-4 text-sm text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800" role="alert">
                    @foreach ($errors->all() as $error)
                    {{ $error }}
                    @endforeach
                </div>
                @if (request('next_slots'))
                    <div class="flex flex-col items-start p-4 mb-4 text-sm text-blue-800 border border-blue-300 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400 dark:border-blue-800" role="alert">
                        <p>You may choose from the suggested time slots below:</p>
                        <ul>
                            @foreach (request('next_slots') as $nextSlot)
                                <li>{{ $nextSlot }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif 
            @endif

            @if ($emptyTimeSlots)
                <div class="flex items-center p-4 mb-4 text-sm text-yellow-800 border border-yellow-300 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 dark:border-yellow-800" role="alert">
                    {{ $emptyTimeSlots }}
                </div>
            @endif

            <div id="restrict_message"
                class="hidden flex items-center p-4 mb-4 text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
                role="alert"
            >
                <div class="ms-3 text-sm font-medium">
                    Weekends are not allowed.
                </div>
                <button type="button" id="close_restrict_message"
                    class="ms-auto -mx-1.5 -my-1.5 bg-red-50 text-red-500 rounded-lg focus:ring-2 focus:ring-red-400 p-1.5 hover:bg-red-200 inline-flex items-center justify-center h-8 w-8 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700"
                    data-dismiss-target="#alert-2" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                </button>
            </div>

            <div class="mb-4">
                <form class="grid grid-cols-4" action="{{ route('bookings.create', $event->id) }}" method="GET"
                    id="booking_form">
                    <div class="mr-4">
                        <label for="booking_timezone" class="block font-medium text-gray-700">Preferred Timezone:</label>
                        <select name="booking_timezone" id="booking_timezone" class="border rounded p-2 w-full"
                            onchange="document.getElementById('booking_form').submit()" 
                            required
                        >
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" {{ $timezone === request('booking_timezone', config('app.timezone')) ? 'selected' : '' }}>
                                    {{ $timezone }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mr-4">
                        <label for="booking_date" class="block font-medium text-gray-700">Date:</label>
                        <input type="date" name="booking_date" id="booking_date" class="border rounded p-2"
                            value="{{ $selectedDate }}"
                            min="{{ $dateToday }}" required />
                    </div>

                    <div class="mr-4">
                        <label for="booking_time" class="block font-medium text-gray-700">Time:</label>
                        <select name="booking_time" id="booking_time" class="border rounded p-2 pe-10" required>
                            <option value="" disabled selected>Time</option>
                            @foreach ($timeSlots as $time)
                                <option value="{{ $time['time'] }}" {{ $time['is_available'] ? '' : 'disabled' }}>
                                    {{ \Carbon\Carbon::parse($time['time'])->format('H:i') }} 
                                    {{ $time['is_available'] ? '' : '(Booked)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mr-4 flex items-end justify-end">
                        <button type="submit" name="submit_schedule" value="1" class="px-4 py-2 bg-blue-600 text-white rounded">
                            Confirm Selection
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="mt-8 p-4 bg-white border rounded-lg">
                <h2 class="text-xl font-bold mb-4">Confirm Your Booking</h2>
                <form action="{{ route('bookings.store', $event->id) }}" method="POST">
                    @csrf
                    <p><strong>Event:</strong> {{ $event->name }}</p>
                    <p><strong>Date:</strong> {{ request('booking_date') }}</p>
                    <p><strong>Time:</strong> 
                        {{ \Carbon\Carbon::parse(request('booking_time'))->format('H:i') }}
                    </p>
                    <input type="hidden" name="event_id" value="{{ $event->id }}">
                    <input type="hidden" name="booking_timezone" value="{{ request('booking_timezone') }}">
                    <input type="hidden" name="booking_date" value="{{ request('booking_date') }}">
                    <input type="hidden" name="booking_time" value="{{ request('booking_time') }}">

                    <div class="grid grid-cols-3 gap-5 py-5">
                        <div class="flex flex-col">
                            <label for="attendee_name">Name:</label>
                            <input type="text" name="attendee_name" id="attendee_name" class="border rounded p-2" required>
                        </div>
                        <div class="flex flex-col">
                            <label for="attendee_email">Email:</label>
                            <input type="email" name="attendee_email" id="attendee_email" class="border rounded p-2" required>
                        </div>
                        <div class="flex flex-col justify-end">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">
                                Confirm Booking
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
    <a href="/bookings" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">
        Back to Bookings
    </a>
</x-guest-layout>