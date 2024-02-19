<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use OpenSearch\Namespaces\CatNamespace;
use OpenSearch\Namespaces\ClusterNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Elasticsearch\Profiler\ClientProfiler;
use Shopware\Elasticsearch\Profiler\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(DataCollector::class)]
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

        $adminClient = $this->createMock(ClientProfiler::class);
        $adminClient
            ->method('getCalledRequests')
            ->willReturn([
                ['time' => 0.4],
                ['time' => 0.5],
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

        $adminClient
            ->method('cluster')
            ->willReturn($clusterMock);

        $adminClient
            ->method('cat')
            ->willReturn($catMock);

        $collector = new DataCollector(
            true,
            true,
            $client,
            $adminClient
        );

        static::assertSame('elasticsearch', $collector->getName());

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(1500, $collector->getTime());
        static::assertEquals(5, $collector->getRequestAmount());
        static::assertCount(5, $collector->getRequests());
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
            true,
            $client,
            $client
        );

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(1200, $collector->getTime());

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
            false,
            $client,
            $client
        );

        $collector->collect(
            new Request(),
            new Response()
        );

        static::assertEquals(0, $collector->getTime());
    }

    public function testCollectAdminSource(): void
    {
        $client = $this->createMock(ClientProfiler::class);
        $client
            ->method('getCalledRequests')
            ->willReturn([]);

        $adminClient = $this->createMock(ClientProfiler::class);
        $adminClient
            ->method('getCalledRequests')
            ->willReturn([
                ['time' => 0.4],
                ['time' => 0.5],
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

        $adminClient
            ->method('cluster')
            ->willReturn($clusterMock);

        $adminClient
            ->method('cat')
            ->willReturn($catMock);

        $request = new Request();
        $context = new Context(new AdminApiSource('admin'));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $collector = new DataCollector(
            true,
            true,
            $client,
            $adminClient
        );

        $collector->collect(
            $request,
            new Response()
        );

        static::assertEquals(900, $collector->getTime());
        static::assertEquals(2, $collector->getRequestAmount());
        static::assertCount(2, $collector->getRequests());

        $client = $this->createMock(ClientProfiler::class);
        $client
            ->method('getCalledRequests')
            ->willReturn([
                ['time' => 0.1],
                ['time' => 0.2],
                ['time' => 0.3],
            ]);

        $client
            ->method('cluster')
            ->willReturn($clusterMock);

        $client
            ->method('cat')
            ->willReturn($catMock);

        $collector = new DataCollector(
            true,
            true,
            $client,
            $adminClient
        );

        $collector->collect(
            $request,
            new Response()
        );

        static::assertEquals(1500, $collector->getTime());
        static::assertEquals(5, $collector->getRequestAmount());
        static::assertCount(5, $collector->getRequests());
    }
}
