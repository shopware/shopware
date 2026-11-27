<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\DataAbstractionLayer;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProfilingEntitySearcher::class)]
class ProfilingEntitySearcherTest extends TestCase
{
    public function testRecordsTheCallAndReturnsTheInnerResult(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $result = new IdSearchResult(0, [], $criteria, $context);

        $inner = static::createMock(EntitySearcherInterface::class);
        $inner->expects($this->once())->method('search')->with(static::isInstanceOf(ProductDefinition::class), $criteria, $context)->willReturn($result);

        $configuration = new Configuration();
        $configuration->setMiddlewares([new ProfilingMiddleware(new BacktraceDebugDataHolder(['default']))]);
        $connection = static::createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($configuration);
        $collector = new DalCollector($connection);

        static::assertSame($result, (new ProfilingEntitySearcher($inner, $collector))->search(new ProductDefinition(), $criteria, $context));

        $collector->lateCollect();
        $call = $collector->getCalls()[0];

        static::assertSame('product', $call['entity']);
        static::assertSame([DalCollector::OPERATION_SEARCH_IDS], array_column($call['operations'], 'operation'));
    }
}
