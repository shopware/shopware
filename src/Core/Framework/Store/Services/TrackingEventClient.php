<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;

/**
 * @package merchant-services
 *
 * @internal
 */
class TrackingEventClient
{
    private Client $client;

    private InstanceService $instanceService;

    public function __construct(Client $client, InstanceService $instanceService)
    {
        $this->client = $client;
        $this->instanceService = $instanceService;
    }

    /**
     * @param mixed[] $additionalData
     *
     * @return array<string, mixed>|null
     */
    public function fireTrackingEvent(string $eventName, array $additionalData = []): ?array
    {
        if (!$this->instanceService->getInstanceId()) {
            return null;
        }

        $additionalData['shopwareVersion'] = $this->instanceService->getShopwareVersion();
        $payload = [
            'additionalData' => $additionalData,
            'instanceId' => $this->instanceService->getInstanceId(),
            'event' => $eventName,
        ];

        try {
            $response = $this->client->post('/swplatform/tracking/events', ['json' => $payload]);

            return json_decode($response->getBody()->getContents(), true, \JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
        }

        return null;
    }
}
