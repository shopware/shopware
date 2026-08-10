<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Advances the customer number range when customers are imported with an explicit customer
 * number, so that subsequently generated customer numbers (e.g. via admin or storefront
 * registration) do not collide with the imported ones.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CustomerNumberRangeSubscriber implements EventSubscriberInterface
{
    private const NUMBER_RANGE_TYPE = 'customer';

    /**
     * Only the plain incremental pattern maps a customer number directly to the range's
     * increment value. For any other pattern (prefixes, dates, ...) advancing the range
     * from an imported number cannot be done safely, so it is skipped.
     */
    private const SUPPORTED_PATTERN = '{n}';

    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractIncrementStorage $incrementStorage,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportAfterImportRecordEvent::class => 'onAfterImportRecord',
        ];
    }

    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== CustomerDefinition::ENTITY_NAME) {
            return;
        }

        $record = $event->getRecord();

        $customerNumber = $record['customerNumber'] ?? null;
        if (!\is_string($customerNumber) || !ctype_digit($customerNumber)) {
            return;
        }

        $salesChannelId = $record['salesChannelId'] ?? null;
        if ($salesChannelId === null && isset($record['salesChannel']) && \is_array($record['salesChannel'])) {
            $salesChannelId = $record['salesChannel']['id'] ?? null;
        }
        if ($salesChannelId !== null && !\is_string($salesChannelId)) {
            return;
        }

        $config = $this->getNumberRangeConfiguration($salesChannelId);
        if ($config === null || $config['pattern'] !== self::SUPPORTED_PATTERN) {
            return;
        }

        $importedValue = (int) $customerNumber;
        $currentValue = $this->incrementStorage->list()[$config['id']] ?? 0;

        // Only ever move the counter forward, never generate a lower (potentially duplicated) value.
        if ($importedValue <= $currentValue) {
            return;
        }

        $this->incrementStorage->set($config['id'], $importedValue);
    }

    /**
     * Resolves the customer number range that applies for the given sales channel, mirroring the
     * lookup used by {@see \Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator}.
     *
     * @return array{id: string, pattern: string}|null
     */
    private function getNumberRangeConfiguration(?string $salesChannelId): ?array
    {
        if ($salesChannelId !== null) {
            $config = $this->connection->fetchAssociative(
                'SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                LEFT JOIN number_range_sales_channel ON number_range.id = number_range_sales_channel.number_range_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (
                    number_range_sales_channel.sales_channel_id = :salesChannelId OR number_range_type.global = 1 OR number_range.global = 1
                )
                ORDER BY number_range.global ASC, number_range_type.global ASC',
                ['typeName' => self::NUMBER_RANGE_TYPE, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
            );
        } else {
            $config = $this->connection->fetchAssociative(
                'SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (number_range_type.global = 1 OR number_range.global = 1)
                ORDER BY number_range.global ASC',
                ['typeName' => self::NUMBER_RANGE_TYPE]
            );
        }

        if (!$config) {
            return null;
        }

        /** @var array{id: string, pattern: string} $config */
        return $config;
    }
}
