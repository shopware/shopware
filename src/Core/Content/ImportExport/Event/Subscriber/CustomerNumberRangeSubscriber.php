<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangeConfigService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CustomerNumberRangeSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_DATE_FORMAT = 'Y-m-d';

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly CustomerNumberRangeConfigService $numberPatternConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EntityRepository $customerRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'onBeforeImportRecord',
            ImportExportAfterImportBatchEvent::class => 'onAfterImportBatch',
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

        $salesChannelId = $this->hasValidUuid($record, 'salesChannelId') ? $record['salesChannelId'] : null;
        $patternConfig = $this->numberPatternConfigService->getPatternConfig($salesChannelId);
        if ($patternConfig === null) {
            return;
        }

        if ($this->extractIncrement($patternConfig['pattern'], $customerNumber) === null) {
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

    public function onAfterImportBatch(ImportExportAfterImportBatchEvent $event): void
    {
        if (!$this->isCustomerEntity($event)) {
            return;
        }

        /** @var array<string, int> $highestIncrements */
        $highestIncrements = [];
        $salesChannelIdsForUpdatedCustomers = $this->getSalesChannelIdsForUpdatedCustomers($event);

        foreach ($event->getResult()->results as $writtenContainerEvent) {
            $nestedEvents = $writtenContainerEvent->getEvents();
            if ($nestedEvents === null) {
                continue;
            }

            foreach ($nestedEvents as $nestedEvent) {
                if ($nestedEvent->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                    continue;
                }

                foreach ($nestedEvent->getWriteResults() as $customerWriteResult) {
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

                    $increment = $this->extractIncrement($configuration['pattern'], $customerNumber);
                    if ($increment === null) {
                        continue;
                    }

                    $configurationId = $configuration['id'];
                    $highestIncrements[$configurationId] = \max(
                        $highestIncrements[$configurationId] ?? 0,
                        $increment,
                    );
                }
            }
        }

        foreach ($highestIncrements as $configurationId => $increment) {
            $this->updateNumberRangeMinimum($configurationId, $increment);
        }

        $this->numberPatternConfigService->reset();
    }

    /**
     * @return array<string, string|null>
     */
    private function getSalesChannelIdsForUpdatedCustomers(ImportExportAfterImportBatchEvent $event): array
    {
        $customerIds = [];

        foreach ($event->getResult()->results as $writtenContainerEvent) {
            $nestedEvents = $writtenContainerEvent->getEvents();
            if ($nestedEvents === null) {
                continue;
            }

            foreach ($nestedEvents as $nestedEvent) {
                if ($nestedEvent->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                    continue;
                }

                foreach ($nestedEvent->getWriteResults() as $writeResult) {
                    if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                        continue;
                    }

                    $customerId = $writeResult->getPrimaryKey();
                    if (\is_string($customerId)) {
                        $customerIds[] = $customerId;
                    }
                }
            }
        }

        $customerIds = \array_values(\array_unique($customerIds));
        if ($customerIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $customerIds));
        $customers = $this->customerRepository->search($criteria, $event->getContext())->getEntities();

        $salesChannelIdsByCustomerId = [];
        foreach ($customers as $customer) {
            $salesChannelIdsByCustomerId[$customer->getId()] = $customer->getSalesChannelId();
        }

        return $salesChannelIdsByCustomerId;
    }

    private function isCustomerEntity(ImportExportBeforeImportRecordEvent|ImportExportAfterImportBatchEvent $event): bool
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

    private function createCustomerNumberSearchCriteria(string $customerNumber, ?string $excludedCustomerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));

        if ($excludedCustomerId !== null) {
            $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $excludedCustomerId)]));
        }

        return $criteria;
    }

    private function updateNumberRangeMinimum(string $configurationId, int $value): void
    {
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = GREATEST(`last_value`, :value)',
            [
                'value' => $value,
                'id' => Uuid::fromHexToBytes($configurationId),
                'stateId' => Uuid::randomBytes(),
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function extractIncrement(string $pattern, string $customerNumber): ?int
    {
        if (!\str_contains($pattern, '{n}')) {
            return null;
        }

        $tokens = preg_split('/(\{[^{}]+\})/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return null;
        }

        $memo = [];

        return $this->parsePatternTokens($tokens, $customerNumber, 0, 0, $memo);
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parsePatternTokens(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        array &$memoryArray,
    ): ?int {
        $memoKey = $tokenIndex . ':' . $valueOffset;
        if (\array_key_exists($memoKey, $memoryArray)) {
            return $memoryArray[$memoKey];
        }

        if ($tokenIndex === \count($tokens)) {
            return $memoryArray[$memoKey] = $valueOffset === \strlen($customerNumber) ? 0 : null;
        }

        $token = $tokens[$tokenIndex];
        if (!$this->isPlaceholder($token)) {
            $tokenLength = \strlen($token);
            if (\substr($customerNumber, $valueOffset, $tokenLength) !== $token) {
                return $memoryArray[$memoKey] = null;
            }

            return $memoryArray[$memoKey] = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $tokenLength,
                $memoryArray,
            );
        }

        $placeholder = \substr($token, 1, -1);
        if ($placeholder === 'n') {
            return $memoryArray[$memoKey] = $this->parseIncrementToken(
                $tokens,
                $customerNumber,
                $tokenIndex,
                $valueOffset,
                $memoryArray,
            );
        }

        if ($placeholder === 'date' || \str_starts_with($placeholder, 'date_')) {
            return $memoryArray[$memoKey] = $this->parseDateToken(
                $tokens,
                $customerNumber,
                $tokenIndex,
                $valueOffset,
                $placeholder === 'date' ? self::DEFAULT_DATE_FORMAT : \substr($placeholder, 5),
                $memoryArray,
            );
        }

        return $memoryArray[$memoKey] = null;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parseIncrementToken(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        array &$memoryArray,
    ): ?int {
        $remaining = \strlen($customerNumber) - $valueOffset;
        $maximumIncrement = null;

        for ($length = 1; $length <= $remaining; ++$length) {
            $value = \substr($customerNumber, $valueOffset, $length);
            if (!\ctype_digit($value)) {
                break;
            }

            $followingIncrement = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $length,
                $memoryArray,
            );
            if ($followingIncrement === null) {
                continue;
            }

            $increment = (int) $value;
            $maximumIncrement = $maximumIncrement === null ? $increment : \max($maximumIncrement, $increment, $followingIncrement);
        }

        return $maximumIncrement;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parseDateToken(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        string $format,
        array &$memoryArray,
    ): ?int {
        $remaining = \strlen($customerNumber) - $valueOffset;
        $maximumIncrement = null;

        for ($length = 1; $length <= $remaining; ++$length) {
            $dateValue = \substr($customerNumber, $valueOffset, $length);
            if (!$this->isValidDateValue($dateValue, $format)) {
                continue;
            }

            $followingIncrement = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $length,
                $memoryArray,
            );

            if ($followingIncrement !== null) {
                $maximumIncrement = $maximumIncrement === null ? $followingIncrement : \max($maximumIncrement, $followingIncrement);
            }
        }

        return $maximumIncrement;
    }

    private function isValidDateValue(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format($format) === $value;
    }

    private function isPlaceholder(string $value): bool
    {
        return \strlen($value) > 2 && $value[0] === '{' && $value[-1] === '}';
    }
}
