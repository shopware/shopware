<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use OpenSearch\Client;
use OpenSearch\EndpointFactoryInterface;
use OpenSearch\HttpTransport;
use OpenSearch\Namespaces\NamespaceBuilderInterface;
use OpenSearch\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type RequestInfo array{url: string, request: array<string, mixed>, response: array<string, mixed>, time: float, backtrace: string, client?: string}
 */
#[Package('framework')]
class ClientProfiler extends Client
{
    /**
     * @var list<RequestInfo>
     */
    private array $requests = [];

    private readonly ?UriInterface $baseUri;

    public function __construct(Client $client)
    {
        /** @var array<NamespaceBuilderInterface> $namespaces */
        $namespaces = $client->registeredNamespaces;

        /** @var TransportInterface $transport */
        $transport = self::readProperty($client, 'httpTransport');
        /** @var EndpointFactoryInterface $endpointFactory */
        $endpointFactory = self::readProperty($client, 'endpointFactory');

        parent::__construct($transport, $endpointFactory, $namespaces);

        $this->baseUri = self::resolveBaseUri($transport);
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function search(array $request = [])
    {
        $time = microtime(true);
        $response = parent::search($request);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($request, '_search'),
            'request' => $request,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function msearch(array $params = [])
    {
        $time = microtime(true);
        $response = parent::msearch($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($params, '_msearch'),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    public function resetRequests(): void
    {
        $this->requests = [];
    }

    /**
     * @return list<RequestInfo>
     */
    public function getCalledRequests(): array
    {
        return $this->requests;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function bulk(array $params = [])
    {
        $time = microtime(true);
        $response = parent::bulk($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($params, '_bulk'),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function putScript(array $params = [])
    {
        $time = microtime(true);
        $response = parent::putScript($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleScriptUrl($params),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    private static function readProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty(Client::class, $property);

        return $reflection->getValue($object);
    }

    private static function resolveBaseUri(TransportInterface $transport): ?UriInterface
    {
        if (!$transport instanceof HttpTransport) {
            return null;
        }

        $reflection = new \ReflectionProperty(HttpTransport::class, 'client');
        $httpClient = $reflection->getValue($transport);

        return self::resolveBaseUriFromClient($httpClient);
    }

    private static function resolveBaseUriFromClient(mixed $httpClient): ?UriInterface
    {
        if ($httpClient instanceof GuzzleClient) {
            $reflection = new \ReflectionProperty(GuzzleClient::class, 'config');
            /** @var array<string, mixed> $config */
            $config = $reflection->getValue($httpClient);
            $baseUri = $config['base_uri'] ?? null;

            return $baseUri instanceof UriInterface ? $baseUri : null;
        }

        if ($httpClient instanceof ClientInterface && property_exists($httpClient, 'client')) {
            $reflection = new \ReflectionProperty($httpClient, 'client');

            return self::resolveBaseUriFromClient($reflection->getValue($httpClient));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assembleUrl(array $params, string $endpoint): string
    {
        $index = $params['index'] ?? null;
        unset($params['index'], $params['body']);

        $path = $this->buildPath($index, $endpoint);
        $query = $this->buildQueryString($params);

        return $this->resolveUrl($path, $query);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assembleScriptUrl(array $params): string
    {
        $id = isset($params['id']) ? (string) $params['id'] : '';
        unset($params['id'], $params['body']);

        return $this->resolveUrl('_scripts/' . rawurlencode($id), $this->buildQueryString($params));
    }

    /**
     * @param string|array<int, string>|null $index
     */
    private function buildPath(string|array|null $index, string $endpoint): string
    {
        if ($index === null || $index === '') {
            return $endpoint;
        }

        if (\is_array($index)) {
            $index = implode(',', array_map('trim', $index));
        }

        return $index . '/' . $endpoint;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildQueryString(array $params): string
    {
        if ($params === []) {
            return '';
        }

        return http_build_query(array_map(static function (mixed $value): mixed {
            if ($value === true) {
                return 'true';
            }

            if ($value === false) {
                return 'false';
            }

            return $value;
        }, $params));
    }

    private function resolveUrl(string $path, string $query): string
    {
        $pathWithQuery = $query === '' ? $path : $path . '?' . $query;
        $uri = $this->baseUri !== null
            ? UriResolver::resolve($this->baseUri, new Uri($pathWithQuery))
            : new Uri($pathWithQuery);

        return (string) $uri;
    }
}
