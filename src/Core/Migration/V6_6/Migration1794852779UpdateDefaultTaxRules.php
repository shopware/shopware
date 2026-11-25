<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794852779UpdateDefaultTaxRules extends MigrationStep
{
    private const STANDARD_RATE = 'Standard rate';
    private const REDUCED_RATE = 'Reduced rate';
    private const REDUCED_RATE_2 = 'Reduced rate 2';
    private const ENTIRE_COUNTRY = 'entire_country';

    /**
     * Country ISO => default tax group name => [outdated rate, current rate]
     *
     * @var array<string, array<string, array{int|float, int|float}>>
     */
    private const CHANGED_RATES = [
        'CZ' => [
            self::REDUCED_RATE => [15, 12],
            self::REDUCED_RATE_2 => [10, 12],
        ],
        'EE' => [
            self::STANDARD_RATE => [20, 24],
        ],
        'FI' => [
            self::STANDARD_RATE => [24, 25.5],
            self::REDUCED_RATE => [14, 13.5],
        ],
        'LT' => [
            self::REDUCED_RATE => [9, 5],
        ],
        'RO' => [
            self::STANDARD_RATE => [19, 21],
            self::REDUCED_RATE => [9, 11],
            self::REDUCED_RATE_2 => [5, 11],
        ],
        'SK' => [
            self::STANDARD_RATE => [20, 23],
            self::REDUCED_RATE => [10, 5],
        ],
    ];

    /**
     * @var array<string, int|float>
     */
    private const MISSING_REDUCED_RATE_2 = [
        'SI' => 5,
        'SK' => 5,
    ];

    public function getCreationTimestamp(): int
    {
        return 1794852779;
    }

    public function update(Connection $connection): void
    {
        foreach (self::CHANGED_RATES as $iso => $rates) {
            foreach ($rates as $taxName => [$outdatedRate, $currentRate]) {
                $this->updateRate($connection, $iso, $taxName, $outdatedRate, $currentRate);
            }
        }

        foreach (self::MISSING_REDUCED_RATE_2 as $iso => $rate) {
            $this->addReducedRate2($connection, $iso, $rate);
        }
    }

    private function updateRate(Connection $connection, string $iso, string $taxName, int|float $outdatedRate, int|float $currentRate): void
    {
        $connection->executeStatement(
            'UPDATE `tax_rule`
                INNER JOIN `tax` ON `tax`.`id` = `tax_rule`.`tax_id`
                INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id`
                INNER JOIN `tax_rule_type` ON `tax_rule_type`.`id` = `tax_rule`.`tax_rule_type_id`
            SET `tax_rule`.`tax_rate` = :currentRate
            WHERE `country`.`iso` = :iso
                AND `tax`.`name` = :taxName
                AND `tax_rule_type`.`technical_name` = :ruleType
                AND `tax_rule`.`tax_rate` = :outdatedRate
                AND `tax_rule`.`updated_at` IS NULL',
            [
                'currentRate' => $currentRate,
                'iso' => $iso,
                'taxName' => $taxName,
                'ruleType' => self::ENTIRE_COUNTRY,
                'outdatedRate' => $outdatedRate,
            ]
        );
    }

    private function addReducedRate2(Connection $connection, string $iso, int|float $rate): void
    {
        $connection->executeStatement(
            'INSERT INTO `tax_rule` (`id`, `tax_id`, `tax_rule_type_id`, `country_id`, `tax_rate`, `created_at`)
            SELECT :id, `tax`.`id`, `tax_rule_type`.`id`, `country`.`id`, :rate, :createdAt
            FROM `tax`
                INNER JOIN `country` ON `country`.`iso` = :iso
                INNER JOIN `tax_rule_type` ON `tax_rule_type`.`technical_name` = :ruleType
            WHERE `tax`.`name` = :taxName
                AND EXISTS (
                    SELECT 1
                    FROM `tax_rule` AS `standard_rule`
                        INNER JOIN `tax` AS `standard_tax` ON `standard_tax`.`id` = `standard_rule`.`tax_id`
                    WHERE `standard_rule`.`country_id` = `country`.`id`
                        AND `standard_tax`.`name` = :standardTaxName
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM `tax_rule` AS `existing_rule`
                    WHERE `existing_rule`.`country_id` = `country`.`id`
                        AND `existing_rule`.`tax_id` = `tax`.`id`
                )
            LIMIT 1',
            [
                'id' => Uuid::randomBytes(),
                'rate' => $rate,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'iso' => $iso,
                'ruleType' => self::ENTIRE_COUNTRY,
                'taxName' => self::REDUCED_RATE_2,
                'standardTaxName' => self::STANDARD_RATE,
            ]
        );
    }
}
