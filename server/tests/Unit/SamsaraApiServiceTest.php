<?php

namespace Fleetbase\Samsara\Tests\Unit;

use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Services\SamsaraApiService;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Class SamsaraApiServiceTest
 * 
 * Unit tests for SamsaraApiService
 * 
 * @package Fleetbase\Samsara\Tests\Unit
 */
class SamsaraApiServiceTest extends TestCase
{
    protected SamsaraApiService $apiService;
    protected SamsaraCredential $mockCredential;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->apiService = new SamsaraApiService();
        
        // Create a mock credential
        $this->mockCredential = Mockery::mock(SamsaraCredential::class);
        $this->mockCredential->shouldReceive('getAttribute')
            ->with('api_base_url')
            ->andReturn('https://api.samsara.com');
        $this->mockCredential->shouldReceive('getAuthHeaders')
            ->andReturn([
                'Authorization' => 'Bearer test-token',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testServiceInstantiation()
    {
        $this->assertInstanceOf(SamsaraApiService::class, $this->apiService);
    }

    public function testSetTimeout()
    {
        $this->apiService->setTimeout(60);
        
        // Since timeout is protected, we can't directly test it
        // but we can ensure the method exists and doesn't throw
        $this->assertTrue(method_exists($this->apiService, 'setTimeout'));
    }

    public function testSetRetryAttempts()
    {
        $this->apiService->setRetryAttempts(5);
        
        // Since retryAttempts is protected, we can't directly test it
        // but we can ensure the method exists and doesn't throw
        $this->assertTrue(method_exists($this->apiService, 'setRetryAttempts'));
    }

    public function testServiceHasRequiredMethods()
    {
        $requiredMethods = [
            'getAllVehicles',
            'getVehicle',
            'getVehicleLocations',
            'getVehicleLocationHistory',
            'getVehicleStats',
            'updateVehicle',
            'syncAllVehicles',
            'syncVehicleLocations',
            'testConnection',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->apiService, $method),
                "SamsaraApiService should have method: {$method}"
            );
        }
    }

    public function testTestConnectionReturnsArray()
    {
        // Mock the HTTP client to avoid actual API calls
        $mockClient = Mockery::mock(\GuzzleHttp\Client::class);
        $mockResponse = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        
        $mockResponse->shouldReceive('getBody->getContents')
            ->andReturn('{"data": []}');
        $mockResponse->shouldReceive('getStatusCode')
            ->andReturn(200);
        
        $mockClient->shouldReceive('request')
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->apiService);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->apiService, $mockClient);

        $result = $this->apiService->testConnection($this->mockCredential);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testSyncAllVehiclesReturnsArray()
    {
        // Mock the HTTP client
        $mockClient = Mockery::mock(\GuzzleHttp\Client::class);
        $mockResponse = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        
        $mockResponse->shouldReceive('getBody->getContents')
            ->andReturn('{"data": [], "pagination": {"hasNextPage": false}}');
        $mockResponse->shouldReceive('getStatusCode')
            ->andReturn(200);
        
        $mockClient->shouldReceive('request')
            ->andReturn($mockResponse);

        // Mock the credential's company_uuid
        $this->mockCredential->shouldReceive('getAttribute')
            ->with('company_uuid')
            ->andReturn('test-company-uuid');
        $this->mockCredential->shouldReceive('updateLastSync')
            ->andReturn(true);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->apiService);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->apiService, $mockClient);

        $result = $this->apiService->syncAllVehicles($this->mockCredential);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}

