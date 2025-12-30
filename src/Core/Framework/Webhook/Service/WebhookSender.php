<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * @internal
 */
#[Package('framework')]
class WebhookSender
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    private int $timeout = self::TIMEOUT;
    private int $connectTimeout = self::CONNECT_TIMEOUT;

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function withConnectTimeout(int $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    public function send(WebhookEventMessage $message): ResponseInterface
    {
        $requestOptions = $this->buildRequestOptions($message);

        return $this->client->post($message->getUrl(), $requestOptions);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRequestOptions(WebhookEventMessage $message): array
    {
        $shopwareVersion = $message->getShopwareVersion();
        $payload = $message->getPayload();

        /**
         * @TODO: logic of timetsamp doesn't seem valid - it's bound to the time of sending the request, not the event itself.
         * This is the same in the processor (not sure if there's some reason behind it, need to double check)
         */
        $payload['timestamp'] = time();

        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'sw-version' => $shopwareVersion,
        ];

        if ($message->getLanguageId() && $message->getUserLocale()) {
            $headers = array_merge($headers, [
                AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $message->getLanguageId(),
                AuthMiddleware::SHOPWARE_USER_LANGUAGE => $message->getUserLocale(),
            ]);
        }

        $requestContent = [
            'headers' => $headers,
            'body' => $jsonPayload,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
        ];

        if ($message->getSecret()) {
            $requestContent[AuthMiddleware::APP_REQUEST_TYPE] = [
                AuthMiddleware::APP_SECRET => $message->getSecret(),
            ];
        }

        return $requestContent;
    }
}
