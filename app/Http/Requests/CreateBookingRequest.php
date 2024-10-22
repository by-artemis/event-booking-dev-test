<?php

namespace App\Http\Requests;

use App\Rules\Weekday;
use App\Rules\WeekdayTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class CreateBookingRequest extends FormRequest
{
    protected $redirect;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'booking_timezone' => ['required', 'string'],
            'booking_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                new Weekday, // Custom validation rule for weekdays
                'after_or_equal:today', // Ensure the booking date is not in the past
            ],
            'booking_time' => [
                'required',
                'date_format:H:i:s',
                new WeekdayTime, // Custom validation rule for booking time within 8:00AM to 5:00PM
            ],
            'attendee_name' => ['required', 'max:128'],
            'attendee_email' => ['required', 'email', 'max:64'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $this->redirect = route('bookings.create', ['event' => $this->getEventId()]);
        parent::failedValidation($validator);
    }

    public function getEventId(): int
    {
        return (int) $this->route('event');
    }

    public function getBookingTimezone(): string
    {
        return $this->input('booking_timezone');
    }

    public function getBookingDate(): string
    {
        return $this->input('booking_date');
    }

    public function getBookingTime(): string
    {
        return $this->input('booking_time');
    }

    public function getAttendeeName(): string
    {
        return $this->input('attendee_name');
    }

    public function getAttendeeEmail(): string
    {
        return $this->input('attendee_email');
    }
}
