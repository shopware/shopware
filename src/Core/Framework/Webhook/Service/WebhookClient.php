<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookClient
{
    public const CONNECT_TIMEOUT = 10;

    public const REQUEST_TIMEOUT = 20;

    private const MAX_REDIRECTS = 5;

    public function __construct(
        private ClientInterface $guzzle,
        private ClockInterface $clock,
        private WebhookTargetValidator $targetValidator,
    ) {
    }

    public function send(WebhookRequest $request): WebhookResult
    {
        $start = $this->clock->now()->getTimestamp();

        try {
            $response = $this->sendWithRedirects($request->request, $request->options);
        } catch (TransferException|WebhookException $e) {
            return $this->createFailureResult($e, $this->clock->now()->getTimestamp() - $start);
        }

        return $this->createSuccessResult(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getHeaders(),
            $response->getBody()->getContents(),
            $this->clock->now()->getTimestamp() - $start,
        );
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
        /** @var array<string, int> $startTimes */
        $startTimes = [];

        $requestFactories = [];
        foreach ($requests as $key => $wr) {
            $requestFactories[$key] = function () use ($wr, $key, &$startTimes) {
                $startTimes[$key] = $this->clock->now()->getTimestamp();

                return $this->sendAsyncWithRedirects($wr->request, $wr->options);
            };
        }

        $pool = new Pool($this->guzzle, $requestFactories, [
            'fulfilled' => function (ResponseInterface $response, string|int $key) use (&$results, &$startTimes): void {
                $duration = $this->clock->now()->getTimestamp() - ($startTimes[(string) $key] ?? $this->clock->now()->getTimestamp());

                $results[(string) $key] = $this->createSuccessResult(
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    $response->getHeaders(),
                    $response->getBody()->getContents(),
                    $duration,
                );
            },
            'rejected' => function (\Throwable $reason, string|int $key) use (&$results, &$startTimes): void {
                $duration = $this->clock->now()->getTimestamp() - ($startTimes[(string) $key] ?? $this->clock->now()->getTimestamp());

                $results[(string) $key] = $this->createFailureResult($reason, $duration);
            },
        ]);
        $pool->promise()->wait();

        return $results;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendWithRedirects(RequestInterface $request, array $options): ResponseInterface
    {
        return $this->sendAsyncWithRedirects($request, $options)->wait();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendAsyncWithRedirects(RequestInterface $request, array $options, int $redirects = 0): PromiseInterface
    {
        [$request, $options] = $this->prepareRequest($request, $options, $redirects > 0);

        return $this->guzzle->sendAsync($request, $options)->then(function (ResponseInterface $response) use ($request, $options, $redirects): ResponseInterface|PromiseInterface {
            if (!$this->isRedirectResponse($response)) {
                return $response;
            }

            if ($redirects >= self::MAX_REDIRECTS) {
                throw WebhookException::maximumRedirectsExceeded();
            }

            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                return $response;
            }

            return $this->sendAsyncWithRedirects($this->createRedirectRequest($request, $location), $options, $redirects + 1);
        });
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{RequestInterface, array<string, mixed>}
     */
    private function prepareRequest(RequestInterface $request, array $options, bool $redirect): array
    {
        $target = $this->targetValidator->validate((string) $request->getUri());
        if ($target === null) {
            throw $redirect ? WebhookException::redirectTargetNotAllowed() : WebhookException::targetNotAllowed();
        }

        $options['allow_redirects'] = false;

        $curlOptions = $options['curl'] ?? [];
        if (!\is_array($curlOptions)) {
            $curlOptions = [];
        }

        $resolvePins = $curlOptions[\CURLOPT_RESOLVE] ?? [];
        if (!\is_array($resolvePins)) {
            $resolvePins = [];
        }

        $resolvePins[] = \sprintf('%s:%d:%s', $target->host, $target->port, $this->formatCurlResolveAddress($target->ip));
        $curlOptions[\CURLOPT_RESOLVE] = $resolvePins;
        $options['curl'] = $curlOptions;

        return [$request, $options];
    }

    private function isRedirectResponse(ResponseInterface $response): bool
    {
        return \in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true);
    }

    private function formatCurlResolveAddress(string $ip): string
    {
        return str_contains($ip, ':') ? \sprintf('[%s]', $ip) : $ip;
    }

    private function createRedirectRequest(RequestInterface $request, string $location): RequestInterface
    {
        $uri = UriResolver::resolve($request->getUri(), new Uri($location));

        return $request->withUri($uri);
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function createSuccessResult(int $statusCode, string $reasonPhrase, array $headers, string $body, int $duration): WebhookResult
    {
        return new WebhookResult(
            json_decode($body, true),
            $statusCode,
            $reasonPhrase,
            $headers,
            processingTimeSeconds: $duration,
        );
    }

    private function createFailureResult(\Throwable $e, int $duration): WebhookResult
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
                $duration,
            );
        }

        return new WebhookResult(
            [],
            null,
            null,
            null,
            $e->getMessage(),
            $e,
            $duration,
        );
    }
}
