<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookClient
{
    public const CONNECT_TIMEOUT = 10;

    public const REQUEST_TIMEOUT = 20;

    public function __construct(
        private ClientInterface $guzzle,
    ) {
    }

    public function send(WebhookRequest $request): WebhookResult
    {
        try {
            $response = $this->guzzle->send($request->request, $request->options);
        } catch (TransferException $e) {
            return $this->createFailureResult($e);
        }

        return $this->createSuccessResult($response->getStatusCode(), $response->getReasonPhrase(), $response->getHeaders(), $response->getBody()->getContents());
    }

    /**
     * Send multiple webhook requests in parallel and collect the results.
     *
     * Keys are preserved end-to-end so callers can correlate results deterministically.
     *
     * @param array<string, WebhookRequest> $requests
     *
     * @return array<string, WebhookResult>
     */
    public function sendBatch(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $results = [];

        $pool = new Pool($this->guzzle, array_map(
            fn (WebhookRequest $wr) => fn () => $this->guzzle->sendAsync($wr->request, $wr->options),
            $requests
        ), [
            'fulfilled' => function (ResponseInterface $response, string|int $key) use (&$results): void {
                $results[(string) $key] = $this->createSuccessResult(
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    $response->getHeaders(),
                    $response->getBody()->getContents()
                );
            },
            'rejected' => function (\Throwable $reason, string|int $key) use (&$results): void {
                $results[(string) $key] = $this->createFailureResult($reason);
            },
        ]);
        $pool->promise()->wait();

        return $results;
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function createSuccessResult(int $statusCode, string $reasonPhrase, array $headers, string $body): WebhookResult
    {
        return new WebhookResult(
            json_decode($body, true),
            $statusCode,
            $reasonPhrase,
            $headers,
        );
    }

    private function createFailureResult(\Throwable $e): WebhookResult
    {
        if ($e instanceof RequestException && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $rawBody = $response->getBody()->getContents();

            $body = json_validate($rawBody)
                ? json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR)
                : $rawBody;

            return new WebhookResult(
                $body,
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
