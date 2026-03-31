<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookClient
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    public function __construct(
        private ClientInterface $guzzle,
        private readonly ClockInterface $clock,
    ) {
    }

    public function send(WebhookRequest $request): WebhookResult
    {
        try {
            $response = $this->guzzle->send($request->request, $this->requestOptions($request));
        } catch (TransferException $e) {
            return $this->createFailureResult($e);
        }

        return $this->createSuccessResult($response->getStatusCode(), $response->getReasonPhrase(), $response->getHeaders(), $response->getBody()->getContents());
    }

    /**
     * Send multiple webhook requests in parallel and collect the results.
     *
     * @return array<array-key, WebhookResult>
     */
    public function sendBatch(WebhookRequest ...$requests): array
    {
        if ($requests === []) {
            return [];
        }

        $results = [];

        $pool = new Pool($this->guzzle, array_map(
            fn (WebhookRequest $wr) => fn () => $this->guzzle->sendAsync($wr->request, $this->requestOptions($wr)),
            $requests
        ), [
            'fulfilled' => function (ResponseInterface $response, string|int $key) use (&$results): void {
                $results[$key] = $this->createSuccessResult(
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    $response->getHeaders(),
                    $response->getBody()->getContents()
                );
            },
            'rejected' => function (TransferException $reason, string|int $key) use (&$results): void {
                $results[$key] = $this->createFailureResult($reason);
            },
        ]);
        $pool->promise()->wait();

        return $results;
    }

    public function createRequest(WebhookEventMessage $message): WebhookRequest
    {
        $timestamp = $this->clock->now()->getTimestamp();

        $payload = $message->getPayload();
        $payload['createdTimestamp'] = $message->getCreatedTimestamp();
        $payload['timestamp'] = $timestamp;

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

        return new WebhookRequest(
            $request,
            $headers,
            $jsonPayload,
            $timestamp,
            $message->getSecret(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function requestOptions(WebhookRequest $request): array
    {
        $options = [
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ];

        if ($request->secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $request->secret];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        return json_validate($body) ? json_decode($body, true) : [];
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function createSuccessResult(int $statusCode, string $reasonPhrase, array $headers, string $body): WebhookResult
    {
        return new WebhookResult(
            $this->decodeBody($body),
            $statusCode,
            $reasonPhrase,
            $headers,
        );
    }

    private function createFailureResult(TransferException $e): WebhookResult
    {
        if ($e instanceof RequestException && $e->getResponse() !== null) {
            $response = $e->getResponse();

            return new WebhookResult(
                $this->decodeBody($response->getBody()->getContents()),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $response->getHeaders(),
                $e->getMessage(),
                $e,
            );
        }

        return new WebhookResult(
            [],
            null,
            null,
            null,
            $e->getMessage(),
            $e,
        );
    }
}
