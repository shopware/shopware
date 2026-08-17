<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTarget;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
#[Package('framework')]
final class PinningAppSystemHttpClient extends Client
{
    public function __construct(
        private readonly Client $client,
        private readonly WebhookTargetValidator $targetValidator,
    ) {
    }

    /**
     * @param list<mixed> $args
     */
    public function __call($method, $args)
    {
        $isAsync = str_ends_with($method, 'Async');
        $method = Utils::asciiToUpper($isAsync ? substr($method, 0, -5) : $method);

        return $isAsync
            ? $this->requestAsync($method, ...$args)
            : $this->request($method, ...$args);
    }

    /**
     * @param string|UriInterface $uri
     * @param array<mixed> $options
     */
    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        $uri = $this->resolveUri($uri);
        /** @phpstan-ignore method.deprecated (Guzzle 7 has no non-deprecated replacement) */
        $defaultRedirectOptions = $this->client->getConfig('allow_redirects');
        $redirectOptions = $options['allow_redirects'] ?? $defaultRedirectOptions ?? true;
        $options['allow_redirects'] = false;

        return $this->sendWithRedirects($method, $uri, $options, $redirectOptions);
    }

    /**
     * @param array<mixed> $options
     */
    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $options[RequestOptions::SYNCHRONOUS] = true;

        return $this->requestAsync($method, $uri, $options)->wait();
    }

    /**
     * @param string|UriInterface $uri
     * @param array<mixed> $options
     */
    public function get($uri = '', array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array<mixed> $options
     */
    public function post($uri = '', array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * @param array<mixed> $options
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->requestAsync($request->getMethod(), $request->getUri(), array_replace($options, [
            'headers' => $request->getHeaders(),
            'body' => $request->getBody(),
            'version' => $request->getProtocolVersion(),
        ]));
    }

    /**
     * @param array<mixed> $options
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $options[RequestOptions::SYNCHRONOUS] = true;

        return $this->sendAsync($request, $options)->wait();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->send($request, [
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);
    }

    public function getConfig(?string $option = null)
    {
        return $this->client->getConfig($option);
    }

    /**
     * @param string|UriInterface $uri
     */
    private function resolveUri($uri): UriInterface
    {
        $uri = Utils::uriFor($uri);
        /** @phpstan-ignore method.deprecated (Guzzle 7 has no non-deprecated replacement) */
        $baseUri = $this->client->getConfig('base_uri');

        return $baseUri === null ? $uri : UriResolver::resolve(Utils::uriFor($baseUri), $uri);
    }

    /**
     * @param array<string, mixed> $options
     * @param bool|array<string, mixed> $redirectOptions
     */
    private function sendWithRedirects(string $method, UriInterface $uri, array $options, bool|array $redirectOptions, int $redirects = 0): PromiseInterface
    {
        $target = $this->targetValidator->validate((string) $uri);
        if ($target === null) {
            throw AppException::appSystemRequestTargetNotAllowed();
        }

        $options = $this->pinTarget($options, $target);

        return $this->client->requestAsync($method, $uri, $options)->then(function (ResponseInterface $response) use ($method, $uri, $options, $redirectOptions, $redirects): ResponseInterface|PromiseInterface {
            if ($redirectOptions === false || !\in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $maxRedirects = \is_array($redirectOptions) ? $redirectOptions['max'] ?? 5 : 5;
            if ($redirects >= $maxRedirects) {
                return $response;
            }

            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                return $response;
            }

            $redirectUri = UriResolver::resolve($uri, new Uri($location));
            if ($response->getStatusCode() === 303 || (!\is_array($redirectOptions) || ($redirectOptions['strict'] ?? false) !== true) && \in_array($response->getStatusCode(), [301, 302], true)) {
                $method = 'GET';
                unset($options['body'], $options['headers']['Content-Length'], $options['headers']['Transfer-Encoding']);
            }

            if (UriComparator::isCrossOrigin($uri, $redirectUri)) {
                $options['headers'] = array_filter(
                    $options['headers'] ?? [],
                    static fn (string $name): bool => !\in_array(strtolower($name), ['authorization', 'cookie'], true),
                    \ARRAY_FILTER_USE_KEY,
                );
            }

            return $this->sendWithRedirects($method, $redirectUri, $options, $redirectOptions, $redirects + 1);
        });
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function pinTarget(array $options, WebhookTarget $target): array
    {
        $curlOptions = $options['curl'] ?? [];
        if (!\is_array($curlOptions)) {
            $curlOptions = [];
        }

        $ip = str_contains($target->ip, ':') ? \sprintf('[%s]', $target->ip) : $target->ip;
        $curlOptions[\CURLOPT_RESOLVE] = [\sprintf('%s:%d:%s', $target->host, $target->port, $ip)];
        $options['curl'] = $curlOptions;

        return $options;
    }
}
