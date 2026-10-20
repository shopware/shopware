<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordsEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\CustomerNumberRangeSubscriber;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangePatternMatcher;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
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
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100014);

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );
        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => '100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testSynchronizesInsertedCustomerNumberAfterOneByOneImport(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100014);

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );
        $batchEvent = $this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => '100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        ));

        $subscriber->onAfterImport(new ImportExportAfterImportRecordsEvent(
            $batchEvent->getConfig(),
            $context,
            $batchEvent->getResult(),
        ));
    }

    public function testDoesNotSynchronizeUpdatedCustomer(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );
        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['firstName' => 'Updated'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        )));
    }

    public function testSynchronizesOnlyTheHighestIncrementInTheBatch(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100015);

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
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

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100020);

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$customer])]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
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

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with($updatedConfigurationId, 100020);

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigServiceForSalesChannels([
                $existingSalesChannelId => ['id' => Uuid::randomHex(), 'pattern' => '{n}'],
                $updatedSalesChannelId => ['id' => $updatedConfigurationId, 'pattern' => '{n}'],
            ]),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$customer])]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'customer-id',
                ['customerNumber' => '100020', 'salesChannelId' => $updatedSalesChannelId],
                CustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testSynchronizesHighestIncrementPerNumberRange(): void
    {
        $context = Context::createDefaultContext();
        $firstSalesChannelId = Uuid::randomHex();
        $secondSalesChannelId = Uuid::randomHex();
        $firstConfigurationId = Uuid::randomHex();
        $secondConfigurationId = Uuid::randomHex();
        $writtenValues = [];

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->exactly(2))
            ->method('increaseToAtLeast')
            ->willReturnCallback(function (string $configurationId, int $value) use (&$writtenValues): void {
                $writtenValues[$configurationId] = $value;
            });

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigServiceForSalesChannels([
                $firstSalesChannelId => ['id' => $firstConfigurationId, 'pattern' => '{n}'],
                $secondSalesChannelId => ['id' => $secondConfigurationId, 'pattern' => '{n}'],
            ]),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
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
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
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
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
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

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$existingCustomer])]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
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

    public function testRejectsDuplicateCustomerNumberInTheSalesChannelNumberRangeResolvedByAssociation(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();
        $existingCustomer = new CustomerEntity();
        $existingCustomer->setId('existing-id');
        $existingCustomer->setCustomerNumber('100014');
        $existingCustomer->setSalesChannelId($salesChannelId);

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$existingCustomer])]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigServiceForSalesChannels([
                $salesChannelId => ['id' => $configurationId, 'pattern' => '{n}'],
            ]),
            new CustomerNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            $customerRepository,
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'Customer number "100014" is already used in the selected number range.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            [
                'customerNumber' => '100014',
                'salesChannel' => [
                    'id' => $salesChannelId,
                ],
            ],
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

        $customerRepository = StaticEntityRepository::of(CustomerCollection::class, [new CustomerCollection([$existingCustomer])]);
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new CustomerNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
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

    public function testRejectsCustomerNumberThatDoesNotMatchTheConfiguredPattern(): void
    {
        $context = Context::createDefaultContext();
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('CUSTOMER-{n}-EU'),
            new CustomerNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'Customer number "CUSTOMER-100014-DE" does not match the configured customer number pattern.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['customerNumber' => 'CUSTOMER-100014-DE'],
            [],
            new Config([], [
                'sourceEntity' => CustomerDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testDoesNotSynchronizeUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}'),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => 'C-100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testSkipsSynchronizationForUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            new CustomerNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(CustomerCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'customer-id',
            ['customerNumber' => 'C-any-content-100014'],
            CustomerDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testRejectsImportWithUnknownPlaceholder(): void
    {
        $context = Context::createDefaultContext();
        $subscriber = new CustomerNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            new CustomerNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
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
