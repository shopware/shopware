<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Profiler\ClientProfiler;
use Shopware\Elasticsearch\Profiler\ElasticsearchRequestLog;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchRequestLog::class)]
class ElasticsearchRequestLogTest extends TestCase
{
    public function testCountsAndReturnsTheRequestsOfBothClientsInPanelOrder(): void
    {
        $client = static::createStub(ClientProfiler::class);
        $client->method('getCalledRequests')->willReturn([
            $this->request('http://opensearch:9200/sw_product/_search', ['index' => 'sw_product', 'body' => ['size' => 24]], 0.5),
        ]);

        $adminClient = static::createStub(ClientProfiler::class);
        $adminClient->method('getCalledRequests')->willReturn([
            $this->request('http://opensearch:9200/sw-admin-product-listing/_search', ['body' => ['size' => 10]], 0.25),
            $this->request('http://opensearch:9200/_scripts/numeric_translated_field_sorting', ['id' => 'numeric_translated_field_sorting'], 0.125),
        ]);

        $log = new ElasticsearchRequestLog($client, $adminClient);

        static::assertSame('elasticsearch', $log->getName());
        static::assertSame(['storefront' => 1, 'admin' => 2], $log->getRequestCounts());
        static::assertSame(
            ['url' => 'http://opensearch:9200/sw_product/_search', 'time' => 500.0, 'body' => ['size' => 24], 'position' => 0],
            $log->getRequest('storefront', 0)
        );
        static::assertSame(
            ['url' => 'http://opensearch:9200/_scripts/numeric_translated_field_sorting', 'time' => 125.0, 'body' => ['id' => 'numeric_translated_field_sorting'], 'position' => 2],
            $log->getRequest('admin', 1)
        );
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{url: string, request: array<string, mixed>, response: array<string, mixed>, time: float, backtrace: string}
     */
    private function request(string $url, array $request, float $time): array
    {
        return ['url' => $url, 'request' => $request, 'response' => [], 'time' => $time, 'backtrace' => ''];
    }
}
