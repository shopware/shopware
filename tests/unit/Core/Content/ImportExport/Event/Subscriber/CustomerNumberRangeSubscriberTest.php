<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\CustomerNumberRangeSubscriber;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\System\NumberRange\ValueGenerator\IncrementArrayStorage;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerNumberRangeSubscriber::class)]
class CustomerNumberRangeSubscriberTest extends TestCase
{
    private const RANGE_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAdvancesRangeWhenImportedNumberIsHigher(): void
    {
        $storage = new IncrementArrayStorage([self::RANGE_ID => 10012]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createConnection(['id' => self::RANGE_ID, 'pattern' => '{n}']),
            $storage,
        );

        $subscriber->onAfterImportRecord($this->createEvent([
            'customerNumber' => '100014',
            'salesChannelId' => Uuid::randomHex(),
        ]));

        static::assertSame(100014, $storage->list()[self::RANGE_ID]);
    }

    public function testResolvesSalesChannelFromNestedAssociation(): void
    {
        $storage = new IncrementArrayStorage([self::RANGE_ID => 5]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createConnection(['id' => self::RANGE_ID, 'pattern' => '{n}']),
            $storage,
        );

        $subscriber->onAfterImportRecord($this->createEvent([
            'customerNumber' => '42',
            'salesChannel' => ['id' => Uuid::randomHex()],
        ]));

        static::assertSame(42, $storage->list()[self::RANGE_ID]);
    }

    public function testDoesNotLowerRangeWhenImportedNumberIsNotHigher(): void
    {
        $storage = new IncrementArrayStorage([self::RANGE_ID => 100014]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createConnection(['id' => self::RANGE_ID, 'pattern' => '{n}']),
            $storage,
        );

        $subscriber->onAfterImportRecord($this->createEvent([
            'customerNumber' => '100013',
            'salesChannelId' => Uuid::randomHex(),
        ]));

        static::assertSame(100014, $storage->list()[self::RANGE_ID]);
    }

    public function testIgnoresUnsupportedPattern(): void
    {
        $storage = new IncrementArrayStorage([self::RANGE_ID => 5]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createConnection(['id' => self::RANGE_ID, 'pattern' => 'SW{n}']),
            $storage,
        );

        $subscriber->onAfterImportRecord($this->createEvent([
            'customerNumber' => '100014',
            'salesChannelId' => Uuid::randomHex(),
        ]));

        static::assertSame(5, $storage->list()[self::RANGE_ID]);
    }

    public function testIgnoresMissingNumberRangeConfiguration(): void
    {
        $storage = new IncrementArrayStorage([]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createConnection(false),
            $storage,
        );

        $subscriber->onAfterImportRecord($this->createEvent([
            'customerNumber' => '100014',
            'salesChannelId' => Uuid::randomHex(),
        ]));

        static::assertSame([], $storage->list());
    }

    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('provideNonAdvancingRecords')]
    public function testDoesNotTouchStorageForIrrelevantRecords(string $sourceEntity, array $record): void
    {
        $storage = new IncrementArrayStorage([self::RANGE_ID => 5]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('fetchAssociative');

        $subscriber = new CustomerNumberRangeSubscriber($connection, $storage);

        $subscriber->onAfterImportRecord($this->createEvent($record, $sourceEntity));

        static::assertSame(5, $storage->list()[self::RANGE_ID]);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function provideNonAdvancingRecords(): iterable
    {
        yield 'non customer source entity' => ['product', ['customerNumber' => '100014']];
        yield 'missing customer number' => [CustomerDefinition::ENTITY_NAME, ['salesChannelId' => 'abc']];
        yield 'non numeric customer number' => [CustomerDefinition::ENTITY_NAME, ['customerNumber' => 'SW100014']];
        yield 'non string customer number' => [CustomerDefinition::ENTITY_NAME, ['customerNumber' => 100014]];
    }

    /**
     * @param array<string, mixed>|false $config
     */
    private function createConnection(array|false $config): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($config);

        return $connection;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function createEvent(array $record, string $sourceEntity = CustomerDefinition::ENTITY_NAME): ImportExportAfterImportRecordEvent
    {
        return new ImportExportAfterImportRecordEvent(
            new EntityWrittenContainerEvent($this->context, new NestedEventCollection(), []),
            $record,
            [],
            new Config([], ['sourceEntity' => $sourceEntity], []),
            $this->context,
        );
    }
}
