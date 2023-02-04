<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;

/**
 * @phpstan-import-type RequestInfo from ClientProfiler
 */
#[Package('core')]
class DataCollector extends BaseDataCollector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly bool $adminEnabled,
        private readonly ClientProfiler $client,
        private readonly ClientProfiler $adminClient
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $enabled = $this->enabled;
        $client = $this->client;

        if ($context instanceof Context && $context->getSource() instanceof AdminApiSource) {
            $enabled = $this->adminEnabled;
            $client = $this->adminClient;
        }

        $this->data = [
            'enabled' => $enabled,
            'requests' => $client->getCalledRequests(),
            'time' => 0,
        ];

        if (!$enabled) {
            return;
        }

        foreach ($client->getCalledRequests() as $calledRequest) {
            $this->data['time'] += $calledRequest['time'];
        }

        $this->data['clusterInfo'] = $client->cluster()->health();
        $this->data['indices'] = $client->cat()->indices();
    }

    public function getName(): string
    {
        return 'elasticsearch';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->client->resetRequests();
        $this->adminClient->resetRequests();
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
        return is_countable($this->data['requests']) ? \count($this->data['requests']) : 0;
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

    public function isEnabled(): bool
    {
        return (bool) $this->data['enabled'];
    }
}
