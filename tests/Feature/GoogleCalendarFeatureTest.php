<?php

namespace Tests\Feature;

use Tests\TestCase;
use Google\Client as GoogleClient;
use App\Services\GoogleCalendarService;

class GoogleCalendarFeatureTest extends TestCase
{
    public function __construct($name = 'GoogleCalendarFeatureTest')
    {
        parent::__construct($name);
        $this->createApplication();
    }

    public function setup(): void
    {
        parent::setup();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        restore_error_handler();
        restore_exception_handler();
    }

    public function testRedirectAuth()
    {
        // Mock GoogleCalendarService
        $mockService = $this->createMock(GoogleCalendarService::class);

        // Assume this is the Google Auth URL that we will test for
        $authUrl = 'https://accounts.google.com/o/oauth2/auth';

        // Mock the Google Client
        $mockClient = $this->createMock(GoogleClient::class);

        // Set up the mock to return the expected auth URL
        $mockClient->method('createAuthUrl')
            ->willReturn($authUrl);

        // Mock the GoogleCalendarService and make it return the mock client
        $mockService = $this->getMockBuilder(GoogleCalendarService::class)
            ->onlyMethods(['getClient'])
            ->getMock();

        $mockService->method('getClient')
            ->willReturn($mockClient);

        // Bind the mock to the service container
        $this->instance(GoogleCalendarService::class, $mockService);

        // Call the route
        $response = $this->get(route('google.auth'));

        // Assert that we are redirected to the Google auth URL
        $response->assertRedirect($authUrl);
        $response->assertStatus(302); // Redirection response
    }

    public function testHandleCallback()
    {
        // Mock the GoogleCalendarService
        $mockService = $this->createMock(GoogleCalendarService::class);

        // Set up the mock to handle the `authenticate` method
        $mockService->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo('dummy-code'));

        // Bind the mock to the service container
        $this->instance(GoogleCalendarService::class, $mockService);

        // Simulate the request with an authorization code
        $response = $this->get(route('google.callback', ['code' => 'dummy-code']));

        // Assert that we are redirected to the events index route
        $response->assertRedirect(route('events.index'));

        // Assert that the session contains the success message
        $response->assertSessionHas('success', 'Google Calendar connected successfully!');
    }
}
