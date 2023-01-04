<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;

/**
 * @phpstan-import-type RequestInfo from ClientProfiler
 */
#[Package('core')]
class DataCollector extends BaseDataCollector
{
    private bool $enabled;

    private ClientProfiler $client;

    /**
     * @internal
     */
    public function __construct(bool $enabled, ClientProfiler $client)
    {
        $this->client = $client;
        $this->enabled = $enabled;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'enabled' => $this->enabled,
            'requests' => $this->client->getCalledRequests(),
            'time' => 0,
        ];

        foreach ($this->client->getCalledRequests() as $calledRequest) {
            $this->data['time'] += $calledRequest['time'];
        }

        if (!$this->enabled) {
            return;
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

        foreach ($this->data['requests'] ?? [] as $calledRequest) {
            $time += $calledRequest['time'];
        }

        return (int) ($time * 1000);
    }

    public function getRequestAmount(): int
    {
        return \count($this->data['requests']);
    }

    /**
     * @return RequestInfo[]
     */
    public function getRequests(): array
    {
        return $this->data['requests'] ?? [];
    }

    /**
     * @return array{cluster_name: string, status: string, number_of_nodes: int}
     */
    public function getClusterInfo(): array
    {
        return $this->data['clusterInfo'];
    }

    /**
     * @return array{index: string, status: string, pri: int, rep: int, 'docs.count': int}[]
     */
    public function getIndices(): array
    {
        return $this->data['indices'];
    }
}
