<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\DataAbstractionLayer;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProfilingEntityReader::class)]
class ProfilingEntityReaderTest extends TestCase
{
    public function testRecordsTheCallAndReturnsTheInnerResult(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $result = new ProductCollection();

        $inner = static::createMock(EntityReaderInterface::class);
        $inner->expects($this->once())->method('read')->with(static::isInstanceOf(ProductDefinition::class), $criteria, $context)->willReturn($result);

        $configuration = new Configuration();
        $configuration->setMiddlewares([new ProfilingMiddleware(new BacktraceDebugDataHolder(['default']))]);
        $connection = static::createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($configuration);
        $collector = new DalCollector($connection);

        static::assertSame($result, (new ProfilingEntityReader($inner, $collector))->read(new ProductDefinition(), $criteria, $context));

        $collector->lateCollect();
        $call = $collector->getCalls()[0];

        static::assertSame('product', $call['entity']);
        static::assertSame([DalCollector::OPERATION_READ], array_column($call['operations'], 'operation'));
    }
}
