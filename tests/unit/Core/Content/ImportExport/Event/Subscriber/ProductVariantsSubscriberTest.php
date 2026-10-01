<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\ProductVariantsSubscriber;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

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

    public function testCreatesProductVariantsAndConfiguratorSettings(): void
    {
        $parentId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $mediumOptionId = Uuid::randomHex();
        $largeOptionId = Uuid::randomHex();

        $groupRepository = StaticEntityRepository::of(PropertyGroupCollection::class, [[$groupId]]);
        $optionRepository = StaticEntityRepository::of(PropertyGroupOptionCollection::class, [[$mediumOptionId], [$largeOptionId]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('executeStatement')->willReturn(1);

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(function (array $operations, Context $context) use ($parentId, $groupId, $mediumOptionId, $largeOptionId): SyncResult {
                static::assertSame($this->context, $context);
                static::assertCount(2, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[0]);
                static::assertInstanceOf(SyncOperation::class, $operations[1]);
                static::assertSame(ProductDefinition::ENTITY_NAME, $operations[0]->getEntity());
                static::assertSame(ProductConfiguratorSettingDefinition::ENTITY_NAME, $operations[1]->getEntity());
                $firstOperationPayload = $operations[0]->getPayload();
                $secondOperationPayload = $operations[1]->getPayload();
                static::assertCount(2, $firstOperationPayload);
                static::assertCount(2, $secondOperationPayload);

                $mediumOption = $firstOperationPayload[0];
                self::assertSame($parentId, $mediumOption['parentId']);
                self::assertSame($mediumOptionId, $mediumOption['options'][0]['id']);
                self::assertSame('M', $mediumOption['options'][0]['name']);
                self::assertSame($groupId, $mediumOption['options'][0]['group']['id']);
                self::assertSame('size', $mediumOption['options'][0]['group']['name']);

                $largeOption = $firstOperationPayload[1];
                self::assertSame($parentId, $largeOption['parentId']);
                self::assertSame($largeOptionId, $largeOption['options'][0]['id']);
                self::assertSame('L', $largeOption['options'][0]['name']);
                self::assertSame($groupId, $largeOption['options'][0]['group']['id']);
                self::assertSame('size', $largeOption['options'][0]['group']['name']);

                $mediumConfiguratorSetting = $secondOperationPayload[0];
                self::assertSame($parentId, $mediumConfiguratorSetting['productId']);
                self::assertSame($mediumOptionId, $mediumConfiguratorSetting['optionId']);

                $largeConfiguratorSetting = $secondOperationPayload[1];
                self::assertSame($parentId, $largeConfiguratorSetting['productId']);
                self::assertSame($largeOptionId, $largeConfiguratorSetting['optionId']);

                return new SyncResult([]);
            });

        $subscriber = new ProductVariantsSubscriber($syncService, $connection, $groupRepository, $optionRepository);
        $writeResult = new EntityWriteResult(
            $parentId,
            ['productNumber' => 'parent'],
            ProductDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        );
        $event = $this->createEvent(
            ProductDefinition::ENTITY_NAME,
            ['variants' => 'size: M, L'],
            new EntityWrittenContainerEvent(
                $this->context,
                new NestedEventCollection([
                    new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [$writeResult], $this->context),
                ]),
                []
            )
        );

        $subscriber->onAfterImportRecord($event);
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
