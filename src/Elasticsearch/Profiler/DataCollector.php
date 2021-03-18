<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;

class DataCollector extends BaseDataCollector
{
    private ClientProfiler $client;

    public function __construct(ClientProfiler $client)
    {
        $this->client = $client;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'requests' => $this->client->getCalledRequests(),
            'time' => 0,
        ];

        foreach ($this->client->getCalledRequests() as $calledRequest) {
            $this->data['time'] += $calledRequest['time'];
        }

        $this->data['clusterInfo'] = $this->client->cluster()->health();
        $this->data['indices'] = $this->client->cat()->indices();
    }

    public function getName(): string
    {
        return 'elasticsearch';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->client->resetRequests();
    }

    public function getTime(): float
    {
        $time = 0;

        foreach ($this->data['requests'] as $calledRequest) {
            $time += $calledRequest['time'];
        }

        return (int) ($time * 1000);
    }

    public function getRequestAmount(): int
    {
        return \count($this->data['requests']);
    }

    public function getRequests(): array
    {
        return $this->data['requests'];
    }

    public function getClusterInfo(): array
    {
        return $this->data['clusterInfo'];
    }

    public function getIndices(): array
    {
        return $this->data['indices'];
    }
}
