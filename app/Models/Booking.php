<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Booking extends Model
{
    use HasFactory;
    use Notifiable;

    protected $table = 'bookings';

    protected $fillable = [
        'event_id',
        'attendee_name',
        'attendee_email',
        'booking_date',
        'booking_time',
        'booking_timezone',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
