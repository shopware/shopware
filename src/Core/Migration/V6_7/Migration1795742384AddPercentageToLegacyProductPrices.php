<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795742384AddPercentageToLegacyProductPrices extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    private bool $indexerRegistered = false;

    public function getCreationTimestamp(): int
    {
        return 1795742384;
    }

    public function update(Connection $connection): void
    {
        $this->addMissingPercentages($connection, 'product');
        $this->addMissingPercentages($connection, 'product_price');
    }

    private function addMissingPercentages(Connection $connection, string $table): void
    {
        $after = ['id' => str_repeat("\0", 16), 'versionId' => str_repeat("\0", 16)];

        do {
            // more list prices than percentages: at least one currency of the price has no percentage
            $rows = $connection->fetchAllAssociative(
                'SELECT `id`, `version_id`, `price` FROM `' . $table . '`
                 WHERE (`id` > :id OR (`id` = :id AND `version_id` > :versionId))
                   AND JSON_LENGTH(JSON_EXTRACT(`price`, "$.*.listPrice.gross"))
                       > IFNULL(JSON_LENGTH(JSON_EXTRACT(`price`, "$.*.percentage.gross")), 0)
                 ORDER BY `id`, `version_id`
                 LIMIT :limit',
                [...$after, 'limit' => self::BATCH_SIZE],
                ['limit' => ParameterType::INTEGER]
            );

            if ($rows === []) {
                return;
            }

            // one commit per batch: a commit per row makes large backfills slow
            $connection->transactional(function () use ($connection, $table, $rows): void {
                foreach ($rows as $row) {
                    $this->addPercentages($connection, $table, $row);
                }
            });

            $lastRow = $rows[\count($rows) - 1];
            $after = ['id' => $lastRow['id'], 'versionId' => $lastRow['version_id']];
        } while (\count($rows) === self::BATCH_SIZE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addPercentages(Connection $connection, string $table, array $row): void
    {
        $prices = json_decode((string) $row['price'], true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($prices)) {
            return;
        }

        $changed = false;
        foreach ($prices as $key => $price) {
            $percentage = $this->calculateMissingPercentage($price);
            if ($percentage === null) {
                continue;
            }

            $prices[$key]['percentage'] = $percentage;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        if (!$this->indexerRegistered) {
            // registered before the first write: a re-run after a failure no longer finds the prices written so far
            $this->registerIndexer($connection, 'product.indexer');
            $this->indexerRegistered = true;
        }

        $connection->update(
            $table,
            ['price' => Json::encode($prices)],
            ['id' => $row['id'], 'version_id' => $row['version_id']]
        );
    }

    /**
     * @return array{net: float, gross: float}|null
     */
    private function calculateMissingPercentage(mixed $price): ?array
    {
        if (!\is_array($price) || \is_array($price['percentage'] ?? null)) {
            return null;
        }

        $listNet = (float) ($price['listPrice']['net'] ?? 0);
        $listGross = (float) ($price['listPrice']['gross'] ?? 0);
        if ($listNet <= 0 && $listGross <= 0) {
            return null;
        }

        // as PriceFieldSerializer::encode() calculates it on write since 6.4.4
        return [
            'net' => $listNet > 0 ? round(100 - (float) ($price['net'] ?? 0) / $listNet * 100, 2) : 0.0,
            'gross' => $listGross > 0 ? round(100 - (float) ($price['gross'] ?? 0) / $listGross * 100, 2) : 0.0,
        ];
    }
}
