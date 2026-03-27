<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use OpenSearch\Client;
use OpenSearch\EndpointFactoryInterface;
use OpenSearch\HttpTransport;
use OpenSearch\Namespaces\NamespaceBuilderInterface;
use OpenSearch\TransportInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\LastRequestAwareHttpClientInterface;

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

    private readonly ?LastRequestAwareHttpClientInterface $httpClient;

    public function __construct(Client $client)
    {
        /** @var array<NamespaceBuilderInterface> $namespaces */
        $namespaces = $client->registeredNamespaces;

        /** @var TransportInterface $transport */
        $transport = self::readProperty($client, 'httpTransport');
        /** @var EndpointFactoryInterface $endpointFactory */
        $endpointFactory = self::readProperty($client, 'endpointFactory');

        parent::__construct($transport, $endpointFactory, $namespaces);

        $this->httpClient = self::resolveHttpClient($transport);
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
            'url' => $this->getLastRequestUrl() ?? '',
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
            'url' => $this->getLastRequestUrl() ?? '',
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
            'url' => $this->getLastRequestUrl() ?? '',
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
            'url' => $this->getLastRequestUrl() ?? '',
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

    private static function resolveHttpClient(TransportInterface $transport): ?LastRequestAwareHttpClientInterface
    {
        if (!$transport instanceof HttpTransport) {
            return null;
        }

        $reflection = new \ReflectionProperty(HttpTransport::class, 'client');
        $httpClient = $reflection->getValue($transport);

        if ($httpClient instanceof LastRequestAwareHttpClientInterface) {
            return $httpClient;
        }

        return null;
    }

    private function getLastRequestUrl(): ?string
    {
        $uri = $this->httpClient?->getLastRequestUri();

        return $uri !== null ? (string) $uri : null;
    }
}
