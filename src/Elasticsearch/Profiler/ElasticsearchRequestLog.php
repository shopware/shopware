<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\DataAbstractionLayer\DalRequestLog;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchRequestLog implements DalRequestLog
{
    private const ADMIN_CHANNEL = 'admin';

    private const STOREFRONT_CHANNEL = 'storefront';

    public function __construct(
        private readonly ClientProfiler $client,
        private readonly ClientProfiler $adminClient,
    ) {
    }

    public function getName(): string
    {
        return 'elasticsearch';
    }

    public function getRequestCounts(): array
    {
        return [
            self::STOREFRONT_CHANNEL => \count($this->client->getCalledRequests()),
            self::ADMIN_CHANNEL => \count($this->adminClient->getCalledRequests()),
        ];
    }

    public function getRequest(string $channel, int $index): array
    {
        $admin = $channel === self::ADMIN_CHANNEL;
        $request = ($admin ? $this->adminClient : $this->client)->getCalledRequests()[$index];

        return [
            'url' => $request['url'],
            'time' => $request['time'] * 1000,
            'body' => $request['request']['body'] ?? $request['request'],
            'position' => $admin ? \count($this->client->getCalledRequests()) + $index : $index,
        ];
    }
}
