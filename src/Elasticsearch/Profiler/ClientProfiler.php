<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Elasticsearch\Client;
use Elasticsearch\Connections\ConnectionInterface;
use Elasticsearch\Namespaces\AbstractNamespace;

/**
 * @phpstan-type RequestInfo array{url: string, request: array<mixed>, response: array<mixed>, time: float, backtrace: string}
 */
class ClientProfiler extends Client
{
    /**
     * @var RequestInfo[]
     */
    private array $requests = [];

    public function __construct(Client $client)
    {
        /** @var array<AbstractNamespace> $namespaces */
        $namespaces = $client->registeredNamespaces;

        parent::__construct($client->transport, $client->endpoints, $namespaces);
    }

    /**
     * @param array<mixed> $request
     *
     * @return array<mixed>
     */
    public function search(array $request = [])
    {
        $time = microtime(true);
        $response = parent::search($request);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleElasticsearchUrl($this->transport->getConnection(), $request),
            'request' => $request,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    public function resetRequests(): void
    {
        $this->requests = [];
    }

    /**
     * @return RequestInfo[]
     */
    public function getCalledRequests(): array
    {
        return $this->requests;
    }

    /**
     * @param array{index?: string, body?: array<mixed>} $request
     */
    private function assembleElasticsearchUrl(ConnectionInterface $connection, array $request): string
    {
        $path = $connection->getPath();

        if (isset($request['index'])) {
            if (\is_array($request['index'])) {
                $request['index'] = implode(',', array_map('trim', $request['index']));
            }

            $path .= $request['index'] . '/_search';
            unset($request['index']);
        }

        if (isset($request['body'])) {
            unset($request['body']);
        }

        return sprintf('%s://%s:%d/%s?%s', $connection->getTransportSchema(), $connection->getHost(), $connection->getPort(), $path, http_build_query($request));
    }
}
