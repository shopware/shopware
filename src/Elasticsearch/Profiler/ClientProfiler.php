<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Elasticsearch\Client;
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

    public function search(array $request = [])
    {
        $time = microtime(true);
        $response = parent::search($request);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'request' => $request,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => sprintf('%s:%s', $backtrace[1]['class'], $backtrace[1]['function']),
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
}
