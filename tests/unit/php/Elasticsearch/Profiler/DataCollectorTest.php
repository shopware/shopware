<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use OpenSearch\Namespaces\CatNamespace;
use OpenSearch\Namespaces\ClusterNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;
use Shopware\Elasticsearch\Profiler\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Profiler\DataCollector
 */
class DataCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $client = $this->createMock(ClientProfiler::class);
        $client
            ->method('getCalledRequests')
            ->willReturn([
                ['time' => 0.1],
                ['time' => 0.2],
                ['time' => 0.3],
            ]);

        $clusterMock = $this->createMock(ClusterNamespace::class);
        $clusterMock
            ->method('health')
            ->willReturn(['status' => 'green']);

        $catMock = $this->createMock(CatNamespace::class);
        $catMock
            ->method('indices')
            ->willReturn(['indices' => ['index1' => ['status' => 'green'], 'index2' => ['status' => 'green']]]);

        $client
            ->method('cluster')
            ->willReturn($clusterMock);

        $client
            ->method('cat')
            ->willReturn($catMock);

        $collector = new DataCollector(
            true,
            $client
        );

        static::assertSame('elasticsearch', $collector->getName());

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(600, $collector->getTime());
        static::assertEquals(3, $collector->getRequestAmount());
        static::assertCount(3, $collector->getRequests());
        static::assertEquals(['status' => 'green'], $collector->getClusterInfo());
        static::assertEquals(['indices' => ['index1' => ['status' => 'green'], 'index2' => ['status' => 'green']]], $collector->getIndices());
    }

    public function testReset(): void
    {
        $client = $this->createMock(ClientProfiler::class);
        $client
            ->method('getCalledRequests')
            ->willReturn([
                ['time' => 0.1],
                ['time' => 0.2],
                ['time' => 0.3],
            ]);

        $collector = new DataCollector(
            true,
            $client
        );

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(600, $collector->getTime());

        $collector->reset();
        static::assertCount(0, $collector->getRequests());
        static::assertEquals(0, $collector->getTime());
    }

    public function testDisabled(): void
    {
        $client = $this->createMock(ClientProfiler::class);
        $client
            ->expects(static::never())
            ->method('cluster');

        $collector = new DataCollector(
            false,
            $client
        );

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(0, $collector->getTime());
    }
}
