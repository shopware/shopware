<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\DataAbstractionLayer;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProfilingEntityWriter::class)]
class ProfilingEntityWriterTest extends TestCase
{
    private DalCollector $collector;

    private WriteContext $writeContext;

    protected function setUp(): void
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares([new ProfilingMiddleware(new BacktraceDebugDataHolder(['default']))]);
        $connection = static::createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($configuration);

        $this->collector = new DalCollector($connection);
        $this->writeContext = WriteContext::createFromContext(Context::createDefaultContext());
    }

    /**
     * @param 'upsert'|'insert'|'update'|'delete' $method
     * @param array<string, mixed>|WriteResult $result
     */
    #[DataProvider('writeMethodProvider')]
    public function testRecordsTheWriteAndReturnsTheInnerResult(string $method, array|WriteResult $result): void
    {
        $rawData = [['id' => 'p1']];

        $inner = static::createMock(EntityWriterInterface::class);
        $inner->expects($this->once())->method($method)->with(static::isInstanceOf(ProductDefinition::class), $rawData, $this->writeContext)->willReturn($result);

        $writer = new ProfilingEntityWriter($inner, $this->collector);
        $definition = new ProductDefinition();

        $actual = match ($method) {
            'upsert' => $writer->upsert($definition, $rawData, $this->writeContext),
            'insert' => $writer->insert($definition, $rawData, $this->writeContext),
            'update' => $writer->update($definition, $rawData, $this->writeContext),
            'delete' => $writer->delete($definition, $rawData, $this->writeContext),
        };

        static::assertSame($result, $actual);

        $this->collector->lateCollect();
        $call = $this->collector->getCalls()[0];

        static::assertSame('product', $call['entity']);
        static::assertSame([$method], array_column($call['operations'], 'operation'));
    }

    public static function writeMethodProvider(): \Generator
    {
        yield 'upsert returns the written results per entity' => ['upsert', []];
        yield 'insert returns the written results per entity' => ['insert', []];
        yield 'update returns the written results per entity' => ['update', []];
        yield 'delete returns a write result' => ['delete', new WriteResult([])];
    }

    public function testRecordsTheSyncAndReturnsTheInnerResult(): void
    {
        $operations = [new SyncOperation('write-products', 'product', SyncOperation::ACTION_UPSERT, [['id' => 'p1']])];
        $result = new WriteResult([]);

        $inner = static::createMock(EntityWriterInterface::class);
        $inner->expects($this->once())->method('sync')->with($operations, $this->writeContext)->willReturn($result);

        static::assertSame($result, (new ProfilingEntityWriter($inner, $this->collector))->sync($operations, $this->writeContext));

        $this->collector->lateCollect();

        static::assertSame([DalCollector::OPERATION_SYNC], array_column($this->collector->getCalls()[0]['operations'], 'operation'));
    }
}
