<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

use GuzzleHttp\Client;

/**
 * @internal
 */
class Notifier
{
    public const EVENT_INSTALL_STARTED = 'Installer started';
    public const EVENT_INSTALL_FINISHED = 'Installer finished';

    private string $apiEndPoint;

    private Client $client;

    private UniqueIdGenerator $idGenerator;

    private string $shopwareVersion;

    public function __construct(string $apiEndPoint, UniqueIdGenerator $idGenerator, Client $client, string $shopwareVersion)
    {
        $this->apiEndPoint = $apiEndPoint;
        $this->client = $client;
        $this->idGenerator = $idGenerator;
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
            'instanceId' => $this->idGenerator->getUniqueId(),
            'event' => $eventName,
        ];

        try {
            $this->client->postAsync($this->apiEndPoint . '/swplatform/tracking/events', ['json' => $payload]);
        } catch (\Exception $ex) {
            // ignore
        }
    }
}
