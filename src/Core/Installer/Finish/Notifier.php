<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

use GuzzleHttp\Client;

class Notifier
{
    public const EVENT_INSTALL_STARTED = 'Installer started';
    public const EVENT_INSTALL_FINISHED = 'Installer finished';

    private string $apiEndPoint;

    private Client $client;

    private string $uniqueId;

    private string $shopwareVersion;

    public function __construct(string $apiEndPoint, string $uniqueId, Client $client, string $shopwareVersion)
    {
        $this->apiEndPoint = $apiEndPoint;
        $this->client = $client;
        $this->uniqueId = $uniqueId;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param array<string, string> $additionalInformation
     */
    public function doTrackEvent(string $eventName, array $additionalInformation = []): void
    {
        $additionalInformation['shopwareVersion'] = $this->shopwareVersion;
        $payload = [
            'additionalData' => $additionalInformation,
            'instanceId' => $this->uniqueId,
            'event' => $eventName,
        ];

        try {
            $this->client->postAsync($this->apiEndPoint . '/swplatform/tracking/events', ['json' => $payload]);
        } catch (\Exception $ex) {
            // ignore
        }
    }
}
