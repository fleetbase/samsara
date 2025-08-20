<?php

namespace Fleetbase\Samsara\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Services\SamsaraWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class SamsaraWebhookController
 * 
 * Controller for handling Samsara webhook events
 * 
 * @package Fleetbase\Samsara\Http\Controllers
 */
class SamsaraWebhookController extends Controller
{
    protected SamsaraWebhookService $webhookService;

    public function __construct(SamsaraWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle incoming webhook from Samsara
     *
     * @param Request $request
     * @param string $companyId
     * @return JsonResponse
     */
    public function handle(Request $request, string $companyId): JsonResponse
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('Samsara webhook received', [
                'company_id' => $companyId,
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);

            // Find the credential for this company
            $credential = SamsaraCredential::where('company_uuid', $companyId)
                ->where('is_active', true)
                ->first();

            if (!$credential) {
                Log::warning('No active Samsara credential found for webhook', [
                    'company_id' => $companyId,
                ]);
                
                return response()->json([
                    'message' => 'No active credential found',
                ], 404);
            }

            // Verify webhook signature if secret is configured
            if ($credential->webhook_secret) {
                $isValid = $this->webhookService->verifySignature(
                    $request,
                    $credential->webhook_secret
                );

                if (!$isValid) {
                    Log::warning('Invalid webhook signature', [
                        'company_id' => $companyId,
                        'credential_id' => $credential->public_id,
                    ]);
                    
                    return response()->json([
                        'message' => 'Invalid signature',
                    ], 401);
                }
            }

            // Process the webhook
            $result = $this->webhookService->processWebhook(
                $request->all(),
                $companyId,
                $credential->uuid
            );

            if ($result['success']) {
                return response()->json([
                    'message' => 'Webhook processed successfully',
                    'event_id' => $result['event_id'] ?? null,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Webhook processing failed',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get webhook events for the current company
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEvents(Request $request): JsonResponse
    {
        $events = SamsaraWebhookEvent::where('company_uuid', session('company'))
            ->when($request->filled('event_type'), function ($query) use ($request) {
                return $query->where('event_type', $request->input('event_type'));
            })
            ->when($request->filled('processing_status'), function ($query) use ($request) {
                return $query->where('processing_status', $request->input('processing_status'));
            })
            ->when($request->filled('vehicle_id'), function ($query) use ($request) {
                return $query->whereHas('samsaraVehicle', function ($q) use ($request) {
                    $q->where('samsara_vehicle_id', $request->input('vehicle_id'));
                });
            })
            ->with(['samsaraVehicle'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        return response()->json($events);
    }

    /**
     * Get a specific webhook event
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getEvent(string $id): JsonResponse
    {
        $event = SamsaraWebhookEvent::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->with(['samsaraVehicle', 'credential'])
            ->firstOrFail();

        return response()->json($event);
    }

    /**
     * Retry processing a failed webhook event
     *
     * @param string $id
     * @return JsonResponse
     */
    public function retryEvent(string $id): JsonResponse
    {
        $event = SamsaraWebhookEvent::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        if ($event->isProcessed()) {
            return response()->json([
                'message' => 'Event is already processed',
            ], 400);
        }

        try {
            $result = $this->webhookService->processEvent($event);

            if ($result['success']) {
                return response()->json([
                    'message' => 'Event processed successfully',
                    'event' => $event->fresh(),
                ]);
            } else {
                return response()->json([
                    'message' => 'Event processing failed',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Event processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get webhook statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        $since = now()->subHours($hours);

        $stats = [
            'total_events' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->count(),
            
            'processed_events' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->where('processing_status', 'processed')
                ->count(),
            
            'failed_events' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->where('processing_status', 'failed')
                ->count(),
            
            'pending_events' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->where('processing_status', 'pending')
                ->count(),
            
            'events_by_type' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type'),
            
            'recent_events' => SamsaraWebhookEvent::where('company_uuid', session('company'))
                ->where('created_at', '>=', $since)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['public_id', 'event_type', 'processing_status', 'created_at']),
        ];

        return response()->json($stats);
    }

    /**
     * Test webhook endpoint (for testing purposes)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function test(Request $request): JsonResponse
    {
        // This endpoint can be used to test webhook processing
        // with sample data during development
        
        $samplePayload = [
            'eventId' => 'test-' . uniqid(),
            'eventMs' => now()->timestamp * 1000,
            'eventType' => 'Alert',
            'event' => [
                'alertConditionDescription' => 'Test alert',
                'alertConditionId' => 'TestAlert',
                'details' => 'This is a test webhook event',
                'device' => [
                    'id' => 'test-vehicle-123',
                    'name' => 'Test Vehicle',
                    'serial' => 'TEST123',
                    'vin' => 'TEST123456789',
                ],
                'orgId' => 12345,
                'resolved' => false,
                'startMs' => now()->timestamp * 1000,
                'summary' => 'Test webhook event',
            ],
        ];

        $payload = $request->input('payload', $samplePayload);
        $companyId = $request->input('company_id', session('company'));

        return $this->handle(
            new Request($payload),
            $companyId
        );
    }

    /**
     * Get webhook URL for the current company
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWebhookUrl(Request $request): JsonResponse
    {
        $companyId = session('company');
        $baseUrl = $request->getSchemeAndHttpHost();
        
        $webhookUrl = $baseUrl . '/samsara/webhook/' . $companyId;

        return response()->json([
            'webhook_url' => $webhookUrl,
            'company_id' => $companyId,
            'instructions' => [
                'Configure this URL in your Samsara dashboard',
                'Make sure to set up webhook authentication if needed',
                'Test the webhook using the test endpoint',
            ],
        ]);
    }
}

