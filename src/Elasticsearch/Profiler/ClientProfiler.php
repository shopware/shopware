<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Elasticsearch\Client;
use Elasticsearch\Connections\ConnectionInterface;
use Elasticsearch\Namespaces\AbstractNamespace;

class ClientProfiler extends Client
{
    private array $requests = [];

    public function __construct(Client $client)
    {
        /** @var array<AbstractNamespace> $namespaces */
        $namespaces = $client->registeredNamespaces;

        parent::__construct($client->transport, $client->endpoints, $namespaces);
    }

    /**
     * @return array
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

    public function getCalledRequests(): array
    {
        return $this->requests;
    }

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
