<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WeekdayTime implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $time = Carbon::parse($value);

        if ($time->hour < 8 || ($time->hour > 17 || ($time->hour === 17 && $time->minute > 0))) {
            $fail('The :attribute must be between 8:00 AM and 5:00 PM.');
        }
    }
}
