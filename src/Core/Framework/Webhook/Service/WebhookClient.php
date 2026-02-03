<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Exception\WebhookSendException;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookClient
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    public function __construct(
        private Client $guzzle,
    ) {
    }

    /**
     * Send a single webhook request and return the response data.
     *
     * @throws WebhookSendException
     *
     * @return array{statusCode: int, reasonPhrase: string, headers: array<string, string[]>, body: mixed}
     */
    public function send(WebhookEventMessage $message): array
    {
        $request = $this->buildRequest($message);

        $options = [
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ];

        try {
            $response = $this->guzzle->send($request, $options);
        } catch (TransferException $e) {
            throw $this->createSendException($message->getUrl(), $e);
        }

        $body = $response->getBody()->getContents();

        return [
            'statusCode' => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => json_validate($body) ? json_decode($body, true) : $body,
        ];
    }

    /**
     * Send multiple webhook requests in parallel (fire-and-forget).
     *
     * Note: This method silently ignores all request failures. This is intentional
     * to prevent a single failing webhook from blocking app lifecycle events
     * (install, update, delete). Consider adding error logging in the future.
     *
     * @param list<WebhookEventMessage> $messages
     */
    public function sendBatch(array $messages): void
    {
        $requests = array_map(
            fn (WebhookEventMessage $message) => $this->buildRequest($message),
            $messages
        );

        if (\count($requests) === 0) {
            return;
        }

        $pool = new Pool($this->guzzle, $requests);
        $pool->promise()->wait();
    }

    private function createSendException(string $url, TransferException $e): WebhookSendException
    {
        if (!$e instanceof RequestException) {
            return WebhookException::sendFailed($url, $e);
        }

        $response = $e->getResponse();

        if ($response === null) {
            return WebhookException::sendFailed($url, $e);
        }

        $body = $response->getBody()->getContents();

        return WebhookException::sendFailed(
            $url,
            $e,
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getHeaders(),
            json_validate($body) ? json_decode($body, true) : $body
        );
    }

    private function buildRequest(WebhookEventMessage $message): Request
    {
        $payload = $message->getPayload();
        $payload['timestamp'] = time();

        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'sw-version' => $message->getShopwareVersion(),
            ],
            $message->getWebhookHeaders()
        );

        if ($message->getLanguageId() !== null && $message->getUserLocale() !== null) {
            $headers[AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE] = $message->getLanguageId();
            $headers[AuthMiddleware::SHOPWARE_USER_LANGUAGE] = $message->getUserLocale();
        }

        $request = new Request(
            'POST',
            $message->getUrl(),
            $headers,
            $jsonPayload
        );

        if ($message->getSecret() !== null) {
            $request = $request->withHeader(
                RequestSigner::SHOPWARE_SHOP_SIGNATURE,
                (new RequestSigner())->signPayload($jsonPayload, $message->getSecret())
            );
        }

        return $request;
    }
}
