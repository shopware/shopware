<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\CustomerNumberRangeSubscriber;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Framework\Context;
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
#[CoversClass(CustomerNumberRangeSubscriber::class)]
class CustomerNumberRangeSubscriberTest extends TestCase
{
    public function testSynchronizesInsertedCustomerNumber(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement');
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01 00:00:00'));

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            $clock,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );
        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => '100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testDoesNotSynchronizeUpdatedCustomer(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );
        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['firstName' => 'Updated'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        )));
    }

    public function testSynchronizesOnlyTheHighestIncrementInTheBatch(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::anything(),
                static::callback(static fn (array $parameters): bool => $parameters['value'] === 100015),
            );

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id-1',
                ['customerNumber' => '100014'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
            new EntityWriteResult(
                'customer-id-2',
                ['customerNumber' => '100015'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
            new EntityWriteResult(
                'customer-id-3',
                ['customerNumber' => '100013'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ));
    }

    public function testSynchronizesUpdatedCustomerUsingItsExistingSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setSalesChannelId($salesChannelId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::anything(),
                static::callback(static fn (array $parameters): bool => $parameters['value'] === 100020),
            );

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$customer])]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id',
                ['customerNumber' => '100020'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testPrefersSalesChannelFromUpdatedPayload(): void
    {
        $context = Context::createDefaultContext();
        $existingSalesChannelId = Uuid::randomHex();
        $updatedSalesChannelId = Uuid::randomHex();
        $updatedConfigurationId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setSalesChannelId($existingSalesChannelId);
        $writtenValues = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $parameters) use (&$writtenValues): int {
                $writtenValues[] = [
                    'id' => Uuid::fromBytesToHex($parameters['id']),
                    'value' => $parameters['value'],
                ];

                return 1;
            });

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigServiceForSalesChannels([
                $existingSalesChannelId => ['id' => Uuid::randomHex(), 'pattern' => '{n}'],
                $updatedSalesChannelId => ['id' => $updatedConfigurationId, 'pattern' => '{n}'],
            ]),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$customer])]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id',
                ['customerNumber' => '100020', 'salesChannelId' => $updatedSalesChannelId],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));

        static::assertSame([
            ['id' => $updatedConfigurationId, 'value' => 100020],
        ], $writtenValues);
    }

    public function testSynchronizesHighestIncrementPerNumberRange(): void
    {
        $context = Context::createDefaultContext();
        $firstSalesChannelId = Uuid::randomHex();
        $secondSalesChannelId = Uuid::randomHex();
        $firstConfigurationId = Uuid::randomHex();
        $secondConfigurationId = Uuid::randomHex();
        $writtenValues = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $parameters) use (&$writtenValues): int {
                $writtenValues[Uuid::fromBytesToHex($parameters['id'])] = $parameters['value'];

                return 1;
            });

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigServiceForSalesChannels([
                $firstSalesChannelId => ['id' => $firstConfigurationId, 'pattern' => '{n}'],
                $secondSalesChannelId => ['id' => $secondConfigurationId, 'pattern' => '{n}'],
            ]),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id-1',
                ['customerNumber' => '100014', 'salesChannelId' => $firstSalesChannelId],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
            new EntityWriteResult(
                'customer-id-2',
                ['customerNumber' => '100016', 'salesChannelId' => $firstSalesChannelId],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
            new EntityWriteResult(
                'customer-id-3',
                ['customerNumber' => '200010', 'salesChannelId' => $secondSalesChannelId],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ));

        static::assertSame([
            $firstConfigurationId => 100016,
            $secondConfigurationId => 200010,
        ], $writtenValues);
    }

    public function testDoesNotSynchronizeUpdateForUnknownCustomer(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'unknown-customer-id',
                ['customerNumber' => '100020'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testDoesNotSynchronizeDeletedCustomer(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id',
                ['customerNumber' => '100020'],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE,
            ),
        ));
    }

    public function testRejectsDuplicateCustomerNumberInTheSameNumberRange(): void
    {
        $context = Context::createDefaultContext();
        $existingCustomer = new CustomerEntity();
        $existingCustomer->setId('existing-id');
        $existingCustomer->setCustomerNumber('100014');
        $existingCustomer->setSalesChannelId(Uuid::randomHex());
        $salesChannelId = $existingCustomer->getSalesChannelId();

        $connection = static::createStub(Connection::class);
        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$existingCustomer])]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            $customerRepository,
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'Customer number "100014" is already used in the selected number range.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['customerNumber' => '100014', 'salesChannelId' => $salesChannelId],
            [],
            new Config([], [
                'sourceEntity' => CustomerDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testAllowsAnUpdateOfItsOwnCustomerNumber(): void
    {
        $context = Context::createDefaultContext();
        $customerId = Uuid::randomHex();
        $existingCustomer = new CustomerEntity();
        $existingCustomer->setId($customerId);
        $existingCustomer->setCustomerNumber('100014');
        $existingCustomer->setSalesChannelId(Uuid::randomHex());
        $salesChannelId = $existingCustomer->getSalesChannelId();

        $connection = static::createStub(Connection::class);
        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$existingCustomer])]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            $connection,
            static::createStub(ClockInterface::class),
            $customerRepository,
        );

        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            [
                'id' => $customerId,
                'customerNumber' => '100014',
                'salesChannelId' => $salesChannelId,
            ],
            [],
            new Config([], [
                'sourceEntity' => CustomerDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => true,
            ], []),
            $context,
        ));

        static::assertSame([], $customerRepository->searches);
    }

    #[DataProvider('invalidCustomerNumberPatterns')]
    public function testRejectsCustomerNumberThatDoesNotMatchTheConfiguredPattern(
        string $pattern,
        string $customerNumber,
    ): void {
        $context = Context::createDefaultContext();
        $connection = static::createStub(Connection::class);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService($pattern),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            \sprintf('Customer number "%s" does not match the configured customer number pattern.', $customerNumber)
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['customerNumber' => $customerNumber],
            [],
            new Config([], [
                'sourceEntity' => CustomerDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    /**
     * @return iterable<string, array{pattern: string, customerNumber: string}>
     */
    public static function invalidCustomerNumberPatterns(): iterable
    {
        yield 'literal suffix does not match' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'customerNumber' => 'CUSTOMER-100014-DE',
        ];

        yield 'missing increment' => [
            'pattern' => 'CUSTOMER-{n}',
            'customerNumber' => 'CUSTOMER-ABC',
        ];

        yield 'invalid date' => [
            'pattern' => '{date}_{n}',
            'customerNumber' => '2026-99-99_100014',
        ];

        yield 'missing increment placeholder' => [
            'pattern' => 'CUSTOMER-{date}',
            'customerNumber' => 'CUSTOMER-2026-08-12',
        ];

        yield 'unknown placeholder' => [
            'pattern' => 'CUSTOMER-{external-value}-{n}',
            'customerNumber' => 'CUSTOMER-value-100014',
        ];
    }

    public function testDoesNotSynchronizeUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}'),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => 'C-100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testSkipsSynchronizationForUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => 'C-any-content-100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testRejectsImportWithUnknownPlaceholder(): void
    {
        $context = Context::createDefaultContext();
        $connection = static::createStub(Connection::class);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'Customer number "C-any-content-100014" does not match the configured customer number pattern.'
        ));

        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['customerNumber' => 'C-any-content-100014'],
            [],
            new Config([], [
                'sourceEntity' => CustomerDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testSynchronizesIncrementWhenDateFollowsIncrement(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::anything(),
                static::callback(static fn (array $parameters): bool => $parameters['value'] === 100014),
            );

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{n}{date_Ymd}'),
            $connection,
            static::createStub(ClockInterface::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => 'C-10001420260811'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    #[DataProvider('customerNumberPatterns')]
    public function testSynchronizesIncrementForDifferentCustomerNumberPatterns(
        string $pattern,
        string $customerNumber,
        int $expectedIncrement,
    ): void {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::anything(),
                static::callback(static fn (array $parameters): bool => $parameters['value'] === $expectedIncrement),
            );
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-12 00:00:00'));

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService($pattern),
            $connection,
            $clock,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImportBatch($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => $customerNumber],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    /**
     * @return iterable<string, array{pattern: string, customerNumber: string, expectedIncrement: int}>
     */
    public static function customerNumberPatterns(): iterable
    {
        yield 'plain increment' => [
            'pattern' => '{n}',
            'customerNumber' => '100014',
            'expectedIncrement' => 100014,
        ];

        yield 'prefix and suffix' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'customerNumber' => 'CUSTOMER-100014-EU',
            'expectedIncrement' => 100014,
        ];

        yield 'increment before date' => [
            'pattern' => '{n}-{date}',
            'customerNumber' => '100014-2026-08-12',
            'expectedIncrement' => 100014,
        ];

        yield 'date before increment' => [
            'pattern' => '{date}_{n}',
            'customerNumber' => '2026-08-12_100014',
            'expectedIncrement' => 100014,
        ];

        yield 'custom date format' => [
            'pattern' => 'DOC-{date_d.m.Y}/{n}',
            'customerNumber' => 'DOC-12.08.2026/100014',
            'expectedIncrement' => 100014,
        ];

        yield 'whitespace and special characters' => [
            'pattern' => 'Customer / {n} (EU) #1',
            'customerNumber' => 'Customer / 100014 (EU) #1',
            'expectedIncrement' => 100014,
        ];

        yield 'multiple increment digits' => [
            'pattern' => 'PREFIX-{n}{date_Ymd}',
            'customerNumber' => 'PREFIX-10001420260812',
            'expectedIncrement' => 100014,
        ];

        yield 'multiple increment digits and adds' => [
            'pattern' => 'PREFIX-99{n}12{date_Ymd}42',
            'customerNumber' => 'PREFIX-99100014122026081242',
            'expectedIncrement' => 100014,
        ];
    }

    private function createBatchEvent(Context $context, EntityWriteResult ...$writeResults): ImportExportAfterImportBatchEvent
    {
        $writtenEvent = new EntityWrittenEvent(CustomerDefinition::ENTITY_NAME, \array_values($writeResults), $context);
        $result = new EntityWrittenContainerEvent($context, new NestedEventCollection([$writtenEvent]), []);

        return new ImportExportAfterImportBatchEvent(
            new Config([], ['sourceEntity' => CustomerDefinition::ENTITY_NAME], []),
            $context,
            new ImportResult([$result], []),
        );
    }

    private function createPatternConfigService(string $pattern = '{n}'): CustomerNumberRangeConfigService
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromHexToBytes('00000000000000000000000000000001'),
            'pattern' => $pattern,
        ]);
        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'setParameter', 'orderBy', 'addOrderBy', 'leftJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new CustomerNumberRangeConfigService($connection);
    }

    /**
     * @param array<string, array{id: string, pattern: string}> $configurations
     */
    private function createPatternConfigServiceForSalesChannels(array $configurations): CustomerNumberRangeConfigService
    {
        $requestedSalesChannelId = 'global';
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturnCallback(
            static function () use (&$requestedSalesChannelId, $configurations): array|false {
                $configuration = $configurations[$requestedSalesChannelId] ?? null;
                if ($configuration === null) {
                    return false;
                }

                return [
                    'id' => Uuid::fromHexToBytes($configuration['id']),
                    'pattern' => $configuration['pattern'],
                ];
            }
        );

        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'orderBy', 'addOrderBy', 'leftJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use (&$requestedSalesChannelId, $queryBuilder): QueryBuilder {
                if ($name === 'salesChannelId') {
                    $requestedSalesChannelId = Uuid::fromBytesToHex($value);
                }

                return $queryBuilder;
            }
        );
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new CustomerNumberRangeConfigService($connection);
    }
}
