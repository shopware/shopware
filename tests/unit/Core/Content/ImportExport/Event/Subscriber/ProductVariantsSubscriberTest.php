<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\ProductVariantsSubscriber;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductVariantsSubscriber::class)]
class ProductVariantsSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReturnsEarlyForNonProductSourceEntity(): void
    {
        $event = $this->createEvent('category');

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForNonStringVariants(): void
    {
        $event = $this->createEvent(ProductDefinition::ENTITY_NAME, ['variants' => ['size: M']]);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForEmptyVariants(): void
    {
        $event = $this->createEvent(ProductDefinition::ENTITY_NAME, ['variants' => '']);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenWrittenEventsAreMissing(): void
    {
        $result = $this->createMock(EntityWrittenContainerEvent::class);
        $result->expects($this->once())->method('getEvents')->willReturn(null);
        $event = $this->createEvent(ProductDefinition::ENTITY_NAME, result: $result);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenNoProductWasWritten(): void
    {
        $writtenEvent = new EntityWrittenEvent('category', [], $this->context);
        $event = $this->createEvent(
            ProductDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenProductWriteResultsAreEmpty(): void
    {
        $writtenEvent = new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [], $this->context);
        $event = $this->createEvent(
            ProductDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForNonStringParentId(): void
    {
        $writeResult = new EntityWriteResult(
            ['id' => Uuid::randomBytes()],
            ['productNumber' => 'parent'],
            ProductDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        );
        $writtenEvent = new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [$writeResult], $this->context);
        $event = $this->createEvent(
            ProductDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    private function createSubscriber(): ProductVariantsSubscriber
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->never())->method('sync');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        return new ProductVariantsSubscriber(
            $syncService,
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createEvent(
        string $sourceEntity,
        array $row = ['variants' => 'size: M'],
        ?EntityWrittenContainerEvent $result = null
    ): ImportExportAfterImportRecordEvent {
        return new ImportExportAfterImportRecordEvent(
            $result ?? new EntityWrittenContainerEvent($this->context, new NestedEventCollection(), []),
            [],
            $row,
            new Config([], ['sourceEntity' => $sourceEntity], []),
            $this->context,
        );
    }
}
