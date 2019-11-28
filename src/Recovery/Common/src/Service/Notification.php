<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

use Shopware\Recovery\Common\HttpClient\Client;

class Notification
{
    /**
     * @var string
     */
    private $apiEndPoint;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $uniqueId;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(string $apiEndPoint, string $uniqueId, Client $client, string $shopwareVersion)
    {
        $this->apiEndPoint = $apiEndPoint;
        $this->client = $client;
        $this->uniqueId = $uniqueId;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param string $eventName
     * @param array  $additionalInformation
     *
     * @return array|false
     */
    public function doTrackEvent($eventName, $additionalInformation = [])
    {
        $additionalInformation['shopwareVersion'] = $this->shopwareVersion;
        $payload = [
            'additionalData' => $additionalInformation,
            'instanceId' => $this->uniqueId,
            'event' => $eventName,
        ];

        try {
            $response = $this->client->post($this->apiEndPoint . '/swplatform/tracking/events', json_encode($payload));
        } catch (\Exception $ex) {
            return false;
        }

        return json_decode($response->getBody(), true) ?: false;
    }
}
