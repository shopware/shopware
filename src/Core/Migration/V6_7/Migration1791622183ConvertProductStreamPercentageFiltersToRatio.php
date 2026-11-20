<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * Since 6.7.10.0, `cheapestPrice.percentage` compares against the price to list price ratio instead of the discount
 * percentage. Dynamic product group conditions written for the discount before the shop ran that release are
 * converted to the ratio, e.g. "at least 10 % off" `{"gte": 10}` becomes `{"lte": 90}`.
 */
#[Package('inventory')]
class Migration1791622183ConvertProductStreamPercentageFiltersToRatio extends MigrationStep
{
    private const PERCENTAGE_FIELDS = ['cheapestPrice.percentage', 'product.cheapestPrice.percentage'];

    /**
     * First shipped with 6.7.10.0, so its execution time is when the shop started to evaluate the ratio
     */
    private const RATIO_RELEASE_MIGRATION = Migration1770740885AddRevisionToConsentState::class;

    private const MIRRORED_RANGE_OPERATORS = [
        'gt' => 'lt',
        'gte' => 'lte',
        'lt' => 'gt',
        'lte' => 'gte',
    ];

    public function getCreationTimestamp(): int
    {
        return 1791622183;
    }

    public function update(Connection $connection): void
    {
        $ratioSince = $connection->fetchOne(
            'SELECT `update` FROM `migration` WHERE `class` = :class AND `update` IS NOT NULL',
            ['class' => self::RATIO_RELEASE_MIGRATION]
        );

        if (!\is_string($ratioSince)) {
            return;
        }

        // conditions created or edited since then were already written for the ratio, converting sets `updated_at`
        $filters = $connection->fetchAllAssociative(
            'SELECT `id`, LOWER(HEX(`product_stream_id`)) AS `stream_id`, `type`, `value`, `parameters`
             FROM `product_stream_filter`
             WHERE `field` IN (:fields) AND COALESCE(`updated_at`, `created_at`) < :ratioSince',
            ['fields' => self::PERCENTAGE_FIELDS, 'ratioSince' => $ratioSince],
            ['fields' => ArrayParameterType::STRING]
        );

        $conversions = [];

        foreach ($filters as $filter) {
            $changes = match ($filter['type']) {
                'range' => $this->convertRange($filter['parameters']),
                'equals', 'equalsAny' => $this->convertValue($filter['value']),
                default => null,
            };

            if ($changes !== null) {
                $conversions[$filter['stream_id']][] = ['filter' => $filter, 'changes' => $changes];
            }
        }

        $compileGroups = false;

        foreach ($conversions as $streamId => $groupConversions) {
            $apiFilter = $this->convertCompiledFilter($connection, (string) $streamId, $groupConversions);
            $compileGroups = $compileGroups || $apiFilter === null;

            $connection->transactional(static function (Connection $connection) use ($streamId, $groupConversions, $apiFilter): void {
                $updatedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                foreach ($groupConversions as ['filter' => $filter, 'changes' => $changes]) {
                    $connection->update('product_stream_filter', [...$changes, 'updated_at' => $updatedAt], ['id' => $filter['id']]);
                }

                if ($apiFilter !== null) {
                    $connection->update('product_stream', ['api_filter' => $apiFilter], ['id' => Uuid::fromHexToBytes((string) $streamId)]);
                }
            });
        }

        if ($conversions === []) {
            return;
        }

        // compiling a group again can run after its products were matched against it, so this is only the fallback
        if ($compileGroups) {
            $this->registerIndexer($connection, 'product_stream.indexer');
        }

        $this->registerIndexer($connection, 'product.indexer', [ProductIndexer::STREAM_UPDATER, ProductIndexer::MANY_TO_MANY_ID_FIELD_UPDATER]);
    }

    /**
     * The products are matched against the compiled filter of a group, so it is converted together with the conditions.
     * Returns null when the compiled filter does not match up with the conditions and the group has to be compiled again.
     *
     * @param non-empty-list<array{filter: array<string, mixed>, changes: array<string, string>}> $conversions
     */
    private function convertCompiledFilter(Connection $connection, string $streamId, array $conversions): ?string
    {
        $apiFilter = $connection->fetchOne(
            'SELECT `api_filter` FROM `product_stream` WHERE `id` = :id AND `invalid` = 0',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );

        $compiled = \is_string($apiFilter) ? json_decode($apiFilter, true) : null;
        if (!\is_array($compiled)) {
            return null;
        }

        $replacements = [];
        $expectedCounts = [];
        foreach ($conversions as ['filter' => $filter, 'changes' => $changes]) {
            $key = $this->conditionKey($this->compiledCondition($filter));
            $replacements[$key] = $this->compiledCondition([...$filter, ...$changes]);
            $expectedCounts[$key] = ($expectedCounts[$key] ?? 0) + 1;
        }

        $foundCounts = [];
        $compiled = $this->replaceConditions($compiled, $replacements, $foundCounts);

        // a kept condition compiled like a converted one is counted as well, there is no telling which of the two is where
        ksort($expectedCounts);
        ksort($foundCounts);
        if ($foundCounts !== $expectedCounts) {
            return null;
        }

        return json_encode($compiled, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $compiled
     * @param array<string, array<string, mixed>> $replacements
     * @param array<string, int> $foundCounts
     *
     * @return array<mixed>
     */
    private function replaceConditions(array $compiled, array $replacements, array &$foundCounts): array
    {
        foreach ($compiled as $index => $condition) {
            if (!\is_array($condition)) {
                continue;
            }

            if (\is_array($condition['queries'] ?? null)) {
                $compiled[$index]['queries'] = $this->replaceConditions($condition['queries'], $replacements, $foundCounts);

                continue;
            }

            if (!\in_array($condition['field'] ?? null, self::PERCENTAGE_FIELDS, true)) {
                continue;
            }

            $key = $this->conditionKey($condition);
            if (!isset($replacements[$key])) {
                continue;
            }

            $compiled[$index] = [...$condition, ...$replacements[$key]];
            $foundCounts[$key] = ($foundCounts[$key] ?? 0) + 1;
        }

        return $compiled;
    }

    /**
     * Mirrors how the product stream indexer compiles a condition: range bounds are decoded, any-of values lose empty and 0 parts
     *
     * @param array<string, mixed> $filter
     *
     * @return array{type: mixed, parameters?: mixed, value?: mixed}
     */
    private function compiledCondition(array $filter): array
    {
        return match ($filter['type']) {
            'range' => ['type' => 'range', 'parameters' => json_decode((string) $filter['parameters'], true)],
            'equalsAny' => ['type' => 'equalsAny', 'value' => implode('|', array_filter(explode('|', (string) $filter['value'])))],
            default => ['type' => $filter['type'], 'value' => $filter['value']],
        };
    }

    /**
     * @param array<mixed> $condition
     */
    private function conditionKey(array $condition): string
    {
        return json_encode([$condition['type'] ?? null, $condition['parameters'] ?? $condition['value'] ?? null], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{parameters: string}|null
     */
    private function convertRange(mixed $parameters): ?array
    {
        if (!\is_string($parameters)) {
            return null;
        }

        $bounds = json_decode($parameters, true);
        if (!\is_array($bounds)) {
            return null;
        }

        $setBounds = array_filter($bounds, static fn (mixed $bound): bool => $bound !== null);
        if ($setBounds === [] || $this->isWrittenForTheRatio($setBounds)) {
            return null;
        }

        $mirrored = [];
        foreach ($bounds as $operator => $discount) {
            if (!isset(self::MIRRORED_RANGE_OPERATORS[$operator])) {
                return null;
            }

            if ($discount !== null && !is_numeric($discount)) {
                return null;
            }

            $mirrored[self::MIRRORED_RANGE_OPERATORS[$operator]] = $discount === null ? null : $this->toRatio($discount);
        }

        return ['parameters' => json_encode($mirrored, \JSON_THROW_ON_ERROR)];
    }

    /**
     * Read as a discount, upper bounds of 100 or more without a lower bound hold for every product with a list price.
     * Such a condition was written for the ratio the administration labels it with, e.g. "less than 100" for any discount.
     *
     * @param non-empty-array<mixed> $setBounds
     */
    private function isWrittenForTheRatio(array $setBounds): bool
    {
        foreach ($setBounds as $operator => $bound) {
            if (!\in_array($operator, ['lt', 'lte'], true) || !is_numeric($bound) || (float) $bound < 100) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{value: string}|null
     */
    private function convertValue(mixed $value): ?array
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        $ratios = [];
        foreach (explode('|', $value) as $discount) {
            if (!is_numeric($discount)) {
                return null;
            }

            $ratios[] = $this->toRatio($discount);
        }

        return ['value' => implode('|', $ratios)];
    }

    private function toRatio(int|float|string $discount): float
    {
        // 100 - x never has more decimals than x, rounding only drops the binary float noise (100 - 99.9 = 0.0999…)
        return round(100 - (float) $discount, 10);
    }
}
