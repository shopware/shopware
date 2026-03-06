<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RevenueReportTool::class)]
class RevenueReportToolTest extends TestCase
{
    public function testReturnsRevenueReport(): void
    {
        $tool = $this->createTool(1500.0, 10, 150.0, [
            new Bucket('2025-03-01', 5, new SumResult('dayRevenue', 800.0)),
            new Bucket('2025-03-02', 5, new SumResult('dayRevenue', 700.0)),
        ]);

        $output = ($tool)('2025-03-01', '2025-03-02');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(['from' => '2025-03-01', 'to' => '2025-03-02'], $data['data']['period']);
        static::assertEqualsWithDelta(1500.0, $data['data']['totalRevenue'], 0.01);
        static::assertSame(10, $data['data']['orderCount']);
        static::assertEqualsWithDelta(150.0, $data['data']['averageOrderValue'], 0.01);
        static::assertCount(2, $data['data']['timeline']);
        static::assertSame('2025-03-01', $data['data']['timeline'][0]['date']);
        static::assertEqualsWithDelta(800.0, $data['data']['timeline'][0]['revenue'], 0.01);
        static::assertSame('2025-03-02', $data['data']['timeline'][1]['date']);
        static::assertEqualsWithDelta(700.0, $data['data']['timeline'][1]['revenue'], 0.01);
    }

    public function testInvalidGroupByReturnsError(): void
    {
        $tool = $this->createTool(0, 0, 0, []);
        $output = ($tool)('2025-01-01', '2025-01-31', 'quarter');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('quarter', $data['error']);
        static::assertStringContainsString('day, week, month', $data['error']);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new RevenueReportTool($registry, $contextProvider);
        $output = ($tool)('2025-01-01', '2025-01-31');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('order:read', $data['error']);
    }

    public function testEmptyResultReturnsZeros(): void
    {
        $tool = $this->createTool(0, 0, 0, []);
        $output = ($tool)('2025-01-01', '2025-01-31');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertEqualsWithDelta(0.0, $data['data']['totalRevenue'], 0.01);
        static::assertSame(0, $data['data']['orderCount']);
        static::assertEqualsWithDelta(0.0, $data['data']['averageOrderValue'], 0.01);
        static::assertSame([], $data['data']['timeline']);
    }

    /**
     * @param list<Bucket> $buckets
     */
    private function createTool(float $totalRevenue, int $orderCount, float $avgValue, array $buckets): RevenueReportTool
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new SumResult('revenue', $totalRevenue),
            new CountResult('orderCount', $orderCount),
            new AvgResult('avgOrderValue', $avgValue),
            new DateHistogramResult('revenueOverTime', $buckets),
        ]);

        $result = new EntitySearchResult(
            'order',
            0,
            new EntityCollection(),
            $aggregations,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('order')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new RevenueReportTool($registry, $contextProvider);
    }
}
