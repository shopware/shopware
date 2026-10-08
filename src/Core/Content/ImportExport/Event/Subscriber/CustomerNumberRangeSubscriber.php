<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordsEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangePatternMatcher;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CustomerNumberRangeSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly CustomerNumberRangeConfigService $numberPatternConfigService,
        private readonly CustomerNumberRangePatternMatcher $numberPatternMatcher,
        private readonly AbstractIncrementStorage $incrementStorage,
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'onBeforeImportRecord',
            ImportExportAfterImportBatchEvent::class => 'onAfterImport',
            ImportExportAfterImportRecordsEvent::class => 'onAfterImport',
        ];
    }

    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        if (!$this->isCustomerEntity($event)) {
            return;
        }

        $record = $event->getRecord();
        $customerNumber = $record['customerNumber'] ?? null;
        if (!\is_string($customerNumber)) {
            return;
        }

        $salesChannelId = $this->getSalesChannelId($record);
        $patternConfig = $this->numberPatternConfigService->getPatternConfig($salesChannelId);
        if ($patternConfig === null) {
            return;
        }

        if ($this->numberPatternMatcher->extractIncrement($patternConfig['pattern'], $customerNumber) === null) {
            throw ImportExportException::processingError(\sprintf(
                'Customer number "%s" does not match the configured customer number pattern.',
                $customerNumber,
            ));
        }

        $excludedCustomerId = null;
        if ($this->isUpdateEvent($event)) {
            $excludedCustomerId = $this->hasValidUuid($record, 'id') ? $record['id'] : null;
        }

        $criteria = $this->createCustomerNumberSearchCriteria($customerNumber, $excludedCustomerId);
        $customersSearchResult = $this->customerRepository->search($criteria, $event->getContext())->getEntities();

        foreach ($customersSearchResult as $customer) {
            if ($excludedCustomerId !== null && $customer->getId() === $excludedCustomerId) {
                continue;
            }

            $existingConfigurationId = $this->numberPatternConfigService->getPatternConfigId($customer->getSalesChannelId());
            if ($existingConfigurationId !== $patternConfig['id']) {
                continue;
            }

            throw ImportExportException::processingError(\sprintf(
                'Customer number "%s" is already used in the selected number range.',
                $customerNumber,
            ));
        }
    }

    public function onAfterImport(ImportExportAfterImportBatchEvent|ImportExportAfterImportRecordsEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== CustomerDefinition::ENTITY_NAME) {
            return;
        }

        /** @var array<string, int> $highestIncrements */
        $highestIncrements = [];
        $salesChannelIdsForUpdatedCustomers = $this->getSalesChannelIdsForUpdatedCustomers($event->getResult(), $event->getContext());

        foreach ($this->customerWriteResults($event->getResult()) as $customerWriteResult) {
            if (!\in_array($customerWriteResult->getOperation(), [
                EntityWriteResult::OPERATION_INSERT,
                EntityWriteResult::OPERATION_UPDATE,
            ], true)) {
                continue;
            }

            $customerNumber = $customerWriteResult->getProperty('customerNumber');
            if (!\is_string($customerNumber)) {
                continue;
            }

            $salesChannelId = $customerWriteResult->getProperty('salesChannelId');
            if (!\is_string($salesChannelId) && $customerWriteResult->getOperation() === EntityWriteResult::OPERATION_UPDATE) {
                $customerId = $customerWriteResult->getPrimaryKey();
                $salesChannelId = \is_string($customerId) ? ($salesChannelIdsForUpdatedCustomers[$customerId] ?? null) : null;
            }

            if ($customerWriteResult->getOperation() === EntityWriteResult::OPERATION_UPDATE && !\is_string($salesChannelId)) {
                continue;
            }

            $salesChannelId = \is_string($salesChannelId) ? $salesChannelId : null;
            $configuration = $this->numberPatternConfigService->getPatternConfig($salesChannelId);
            if ($configuration === null) {
                continue;
            }

            $increment = $this->numberPatternMatcher->extractIncrement($configuration['pattern'], $customerNumber);
            if ($increment === null) {
                continue;
            }

            $configurationId = $configuration['id'];
            $highestIncrements[$configurationId] = \max(
                $highestIncrements[$configurationId] ?? 0,
                $increment,
            );
        }

        foreach ($highestIncrements as $configurationId => $increment) {
            $this->incrementStorage->increaseToAtLeast($configurationId, $increment);
        }

        $this->numberPatternConfigService->reset();
    }

    /**
     * @return list<EntityWriteResult>
     */
    private function customerWriteResults(ImportResult $result): array
    {
        $customerWriteResults = [];

        foreach ($result->results as $writtenContainerEvent) {
            $nestedEvents = $writtenContainerEvent->getEvents();
            if ($nestedEvents === null) {
                continue;
            }

            foreach ($nestedEvents as $nestedEvent) {
                if ($nestedEvent->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                    continue;
                }

                foreach ($nestedEvent->getWriteResults() as $writeResult) {
                    $customerWriteResults[] = $writeResult;
                }
            }
        }

        return $customerWriteResults;
    }

    /**
     * @return array<string, string|null>
     */
    private function getSalesChannelIdsForUpdatedCustomers(ImportResult $result, Context $context): array
    {
        $customerIds = [];

        foreach ($this->customerWriteResults($result) as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $customerId = $writeResult->getPrimaryKey();
            if (\is_string($customerId)) {
                $customerIds[] = $customerId;
            }
        }

        $customerIds = \array_values(\array_unique($customerIds));
        if ($customerIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $customerIds));
        $customers = $this->customerRepository->search($criteria, $context)->getEntities();

        $salesChannelIdsByCustomerId = [];
        foreach ($customers as $customer) {
            $salesChannelIdsByCustomerId[$customer->getId()] = $customer->getSalesChannelId();
        }

        return $salesChannelIdsByCustomerId;
    }

    private function isCustomerEntity(ImportExportBeforeImportRecordEvent $event): bool
    {
        return $event->getConfig()->get('sourceEntity') === CustomerDefinition::ENTITY_NAME;
    }

    private function isUpdateEvent(ImportExportBeforeImportRecordEvent $event): bool
    {
        return $event->getConfig()->get('updateEntities') ?? true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function hasValidUuid(array $record, string $key): bool
    {
        $value = $record[$key] ?? null;

        return \is_string($value) && Uuid::isValid($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function getSalesChannelId(array $record): ?string
    {
        $salesChannelId = $record['salesChannelId'] ?? null;
        if (\is_string($salesChannelId) && Uuid::isValid($salesChannelId)) {
            return $salesChannelId;
        }

        $salesChannel = $record['salesChannel'] ?? null;
        if (!\is_array($salesChannel)) {
            return null;
        }

        $salesChannelId = $salesChannel['id'] ?? null;

        return \is_string($salesChannelId) && Uuid::isValid($salesChannelId) ? $salesChannelId : null;
    }

    private function createCustomerNumberSearchCriteria(string $customerNumber, ?string $excludedCustomerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));

        if ($excludedCustomerId !== null) {
            $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $excludedCustomerId)]));
        }

        return $criteria;
    }
}
