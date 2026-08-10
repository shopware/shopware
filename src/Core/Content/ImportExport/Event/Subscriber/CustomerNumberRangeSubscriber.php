<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CustomerNumberRangeSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array{id: string, pattern: string, start: int}|null>
     */
    private array $configurationCache = [];

    /**
     * @var array<string, int>
     */
    private array $states = [];

    private bool $statesLoaded = false;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportAfterImportRecordEvent::class => 'onAfterImportRecord',
        ];
    }

    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractIncrementStorage $incrementStorage
    ) {
    }

    public function onAfterImportRecord(ImportExportAfterImportRecordEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== CustomerDefinition::ENTITY_NAME) {
            return;
        }

        $record = $event->getRecord();
        $customerNumber = $record['customerNumber'] ?? null;
        if (!\is_string($customerNumber) || $customerNumber === '') {
            return;
        }

        $configuration = $this->getConfiguration($record['salesChannelId'] ?? null);
        if ($configuration === null) {
            return;
        }

        $increment = $this->extractIncrement($customerNumber, $configuration['pattern']);
        if ($increment === null || $increment < $configuration['start']) {
            return;
        }

        $currentValue = $this->getCurrentState($configuration['id'], $configuration['start']);
        if ($increment <= $currentValue) {
            return;
        }

        $this->incrementStorage->set($configuration['id'], $increment);
        $this->states[$configuration['id']] = $increment;
    }

    public function reset(): void
    {
        $this->configurationCache = [];
        $this->states = [];
        $this->statesLoaded = false;
    }

    private function getCurrentState(string $numberRangeId, int $start): int
    {
        if (!$this->statesLoaded) {
            $this->states = $this->incrementStorage->list();
            $this->statesLoaded = true;
        }

        return $this->states[$numberRangeId] ?? $start - 1;
    }

    /**
     * @return array{id: string, pattern: string, start: int}|null
     */
    private function getConfiguration(mixed $salesChannelId): ?array
    {
        $key = \is_string($salesChannelId) ? $salesChannelId : 'global';
        if (\array_key_exists($key, $this->configurationCache)) {
            return $this->configurationCache[$key];
        }

        if (\is_string($salesChannelId) && Uuid::isValid($salesChannelId)) {
            /** @var array{id: string, pattern: string, start: int}|false $configuration */
            $configuration = $this->connection->fetchAssociative('
                SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`, `number_range`.`start`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                LEFT JOIN number_range_sales_channel ON number_range.id = number_range_sales_channel.number_range_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (
                    number_range_sales_channel.sales_channel_id = :salesChannelId OR number_range_type.global = 1 OR number_range.global = 1
                )
                ORDER BY number_range.global ASC, number_range_type.global ASC
            ', [
                'typeName' => CustomerDefinition::ENTITY_NAME,
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
            ]);
        } else {
            /** @var array{id: string, pattern: string, start: int}|false $configuration */
            $configuration = $this->connection->fetchAssociative('
                SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`, `number_range`.`start`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (number_range_type.global = 1 OR number_range.global = 1)
                ORDER BY number_range.global ASC
            ', ['typeName' => CustomerDefinition::ENTITY_NAME]);
        }

        if ($configuration === false) {
            $this->configurationCache[$key] = null;

            return null;
        }

        $configuration['start'] = (int) $configuration['start'];
        $this->configurationCache[$key] = $configuration;

        return $this->configurationCache[$key];
    }

    private function extractIncrement(string $customerNumber, ?string $pattern): ?int
    {
        if (ctype_digit($customerNumber)) {
            return (int) $customerNumber;
        }

        if (!$pattern) {
            return null;
        }

        $parts = preg_split(
            '/([}{])/',
            $pattern,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY
        );

        if (!\is_array($parts)) {
            return null;
        }

        $regex = '';
        $insidePattern = false;
        $hasIncrementPattern = false;

        foreach ($parts as $part) {
            if ($part === '{') {
                $insidePattern = true;

                continue;
            }

            if ($part === '}') {
                $insidePattern = false;

                continue;
            }

            if ($insidePattern) {
                $patternName = explode('_', $part)[0];

                if ($patternName === 'n') {
                    $regex .= '(?P<increment>\d+)';
                    $hasIncrementPattern = true;
                } else {
                    $regex .= '.+?';
                }

                $insidePattern = false;

                continue;
            }

            $regex .= preg_quote($part, '/');
        }

        if (!$hasIncrementPattern) {
            return null;
        }

        if (!preg_match('/^' . $regex . '$/', $customerNumber, $matches)) {
            return null;
        }

        $increment = $matches['increment'] ?? null;
        if (!\is_string($increment) || !ctype_digit($increment)) {
            return null;
        }

        return (int) $increment;
    }
}
