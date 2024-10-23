<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleCalendarService;

class GoogleCalendarController extends Controller
{
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    public function connectToGoogle()
    {
        // Check if user is authenticated and connected to Google Calendar
        if (session('google_access_token')) {
            return redirect()
                ->route('events.index')
                ->with('success', 'You are already connected to Google Calendar!');
        }

        return view('google.connect');
    }

    public function redirectAuth()
    {
        return redirect()->away($this->calendarService->getClient()->createAuthUrl());
    }

    public function handleCallback(Request $request)
    {
        $this->calendarService->authenticate($request->get('code'));
        return redirect()
            ->route('events.index')
            ->with('success', 'Google Calendar connected successfully!');
    }

    public function revokeAccess()
    {
        $this->calendarService->revokeAccess();
    }
}
