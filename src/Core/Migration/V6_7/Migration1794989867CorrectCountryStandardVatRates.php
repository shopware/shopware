<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Corrects the standard VAT rates Migration1589359936AddTaxCountryRules wrote in 2020, verified
 * against the European Commission's TEDB on 2026-11-18. The reduced rates it wrote have drifted
 * too, but which published rate belongs in which of the two reduced slots is a tax judgement no
 * source answers — see RELEASE_INFO-6.7.md.
 *
 * @internal
 */
#[Package('checkout')]
class Migration1794989867CorrectCountryStandardVatRates extends MigrationStep
{
    /**
     * [ISO code, rate shipped since 2020, rate in force].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const CORRECTIONS = [
        ['EE', '20', '24'],
        ['FI', '24', '25.5'],
        ['RO', '19', '21'],
        ['SK', '20', '23'],
    ];

    public function getCreationTimestamp(): int
    {
        return 1794989867;
    }

    public function update(Connection $connection): void
    {
        foreach (self::CORRECTIONS as [$iso, $previousRate, $currentRate]) {
            // Every condition guards against a different way of corrupting a shop's tax setup.
            // `country`.`iso` is nullable and non-unique, so resolving the id first would pick one
            // of several rows. The tax class and rule type narrow this to the rows the 2020
            // migration wrote; `updated_at IS NULL` alone would not, because the DAL only sets it
            // on an update, so a rule a shop owner added by hand has it NULL too. The tolerance is
            // needed because `tax_rate` is a DOUBLE(10,3). `updated_at` stays NULL afterwards, or
            // the row would look shop-owner-edited and the next rate change could not correct it.
            $connection->executeStatement(
                'UPDATE `tax_rule` `tr`
                 INNER JOIN `country` `c` ON `c`.`id` = `tr`.`country_id`
                 INNER JOIN `tax` `t` ON `t`.`id` = `tr`.`tax_id`
                 INNER JOIN `tax_rule_type` `trt` ON `trt`.`id` = `tr`.`tax_rule_type_id`
                 SET `tr`.`tax_rate` = :currentRate
                 WHERE `c`.`iso` = :iso
                   AND `t`.`name` = :taxClass
                   AND `trt`.`technical_name` = :ruleType
                   AND ABS(`tr`.`tax_rate` - :previousRate) < 0.001
                   AND `tr`.`updated_at` IS NULL',
                [
                    'currentRate' => $currentRate,
                    'iso' => $iso,
                    'taxClass' => 'Standard rate',
                    'ruleType' => 'entire_country',
                    'previousRate' => $previousRate,
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do
    }
}
