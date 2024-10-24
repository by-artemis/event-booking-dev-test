<?php

namespace Tests\Unit;

use Log;
use Tests\TestCase;
use Google\Client as GoogleClient;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Google\Service\Calendar as GoogleCalendar;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GoogleCalendarTest extends TestCase
{
    use WithFaker;
    use DatabaseTransactions;

    private ?GoogleCalendarService $googleCalendarService = null;
    private ?GoogleCalendar $googleCalendar = null;

    private ?MockObject $googleClientMock = null;
    private string $accessToken;
    private string $refreshToken;

    public function __construct($name = 'GoogleCalendarTest')
    {
        parent::__construct($name);
        $this->createApplication();
    }

    public function setUp(): void
    {
        parent::setUp();

        // Mock the Google Client
        $this->googleClientMock = $this->createMock(GoogleClient::class);

        // Mock the authorization code and access token
        $this->accessToken = 'test_access_token';
        $this->refreshToken = 'test_refresh_token';
        
        $tokens = [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken
        ];

        $authCode = 'test_auth_code';

        // Set up the mock to fetch the access token with the auth code
        $this->googleClientMock->expects($this->once())
            ->method('fetchAccessTokenWithAuthCode')
            ->with($authCode)
            ->willReturn($tokens);

        // Set up the mock to fetch the refresh token using the refresh token
        $this->googleClientMock->expects($this->once())
            ->method('fetchAccessTokenWithRefreshToken')
            ->with($tokens['refresh_token']);

        // Mock the session 'put' and 'get' methods
        Session::shouldReceive('put')
            ->once()
            ->with('google_access_token', $tokens['access_token']);

        Session::shouldReceive('get')
            ->once()
            ->with('google_access_token')
            ->andReturn($tokens['access_token']);

        // Mock the logger
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        // Inject the mock client into the service
        $this->googleCalendarService = new GoogleCalendarService($this->googleClientMock);
        $this->googleCalendarService->authenticate($authCode);

        $this->googleCalendar = new GoogleCalendar($this->googleClientMock);

    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAuthenticate()
    {
        // Assert that the access token was stored in the session
        $this->assertEquals(Session::get(
            'google_access_token'),
            'test_access_token');
    }

    public function testGetClient()
    {
        Session::shouldReceive('get')
            ->once()
            ->with('google_access_token')
            ->andReturn('test_access_token'); 

        // Set expectation for setAccessToken to be called
        $this->googleClientMock->expects($this->once())
            ->method('setAccessToken')
            ->with($this->accessToken);

        // Call the getClient method
        $client = $this->googleCalendarService->getClient();

        // Assert that the client is an instance of GoogleClient
        $this->assertInstanceOf(GoogleClient::class, $client);
    }
}
