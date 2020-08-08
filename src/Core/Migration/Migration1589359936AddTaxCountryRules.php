<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1589359936AddTaxCountryRules extends MigrationStep
{
    /**
     * @var array
     */
    private $countryIds;

    public function getCreationTimestamp(): int
    {
        return 1589359936;
    }

    public function update(Connection $connection): void
    {
        // Taxes have been touched already?
        if (!$this->isMigrationAllowed($connection)) {
            return;
        }

        $this->updateTaxNames($connection);
        $this->createNewTax($connection);
        $this->addCountryTaxRules($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function isMigrationAllowed(Connection $connection): bool
    {
        $count = (int) $connection->executeQuery('SELECT COUNT(id) FROM tax WHERE tax_rate IN (7, 19) AND updated_at IS NULL')->fetchColumn();

        return $count === 2;
    }

    private function updateTaxNames(Connection $connection): void
    {
        $connection->update('tax', ['name' => 'Standard rate'], ['tax_rate' => 19]);
        $connection->update('tax', ['name' => 'Reduced rate'], ['tax_rate' => 7]);
    }

    private function createNewTax(Connection $connection): void
    {
        $connection->insert('tax', ['id' => Uuid::randomBytes(), 'tax_rate' => 0, 'name' => 'Reduced rate 2', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function addCountryTaxRules(Connection $connection): void
    {
        $standardRate = $connection->executeQuery('SELECT id FROM tax WHERE tax_rate = 19')->fetchColumn();
        $reducedRate = $connection->executeQuery('SELECT id FROM tax WHERE tax_rate = 7')->fetchColumn();
        $reducedRate2 = $connection->executeQuery('SELECT id FROM tax WHERE tax_rate = 0')->fetchColumn();
        $this->fetchCountryIds($connection);
        $taxRuleTypeId = $connection->executeQuery('SELECT id FROM tax_rule_type WHERE technical_name = "entire_country"')->fetchColumn();

        foreach ($this->getCountryTaxes() as $isoCode => $countryTax) {
            if (!array_key_exists($isoCode, $this->countryIds)) {
                // country was deleted by shop owner
                continue;
            }

            $connection->insert('tax_rule', [
                'id' => Uuid::randomBytes(),
                'tax_id' => $standardRate,
                'tax_rule_type_id' => $taxRuleTypeId,
                'country_id' => $this->countryIds[$isoCode],
                'tax_rate' => $countryTax['standardRate'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if (array_key_exists('reduced1', $countryTax)) {
                $connection->insert('tax_rule', [
                    'id' => Uuid::randomBytes(),
                    'tax_id' => $reducedRate,
                    'tax_rule_type_id' => $taxRuleTypeId,
                    'country_id' => $this->countryIds[$isoCode],
                    'tax_rate' => $countryTax['reduced1'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (array_key_exists('reduced2', $countryTax)) {
                $connection->insert('tax_rule', [
                    'id' => Uuid::randomBytes(),
                    'tax_id' => $reducedRate2,
                    'tax_rule_type_id' => $taxRuleTypeId,
                    'country_id' => $this->countryIds[$isoCode],
                    'tax_rate' => $countryTax['reduced2'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }
        }
    }

    private function fetchCountryIds(Connection $connection): void
    {
        $countries = $connection->executeQuery('SELECT id, iso FROM country')->fetchAll();

        foreach ($countries as $country) {
            $this->countryIds[$country['iso']] = $country['id'];
        }
    }

    private function getCountryTaxes(): array
    {
        return [
            'BE' => [
                'standardRate' => 21,
                'reduced1' => 12,
                'reduced2' => 6,
            ],
            'BG' => [
                'standardRate' => 20,
                'reduced1' => 9,
            ],
            'CZ' => [
                'standardRate' => 21,
                'reduced1' => 15,
                'reduced2' => 10,
            ],
            'DK' => [
                'standardRate' => 25,
            ],
            'DE' => [
                'standardRate' => 19,
                'reduced1' => 7,
            ],
            'EE' => [
                'standardRate' => 20,
                'reduced1' => 9,
            ],
            'IE' => [
                'standardRate' => 23,
                'reduced1' => 13.5,
                'reduced2' => 9,
            ],
            'GR' => [
                'standardRate' => 24,
                'reduced1' => 13,
                'reduced2' => 6,
            ],
            'ES' => [
                'standardRate' => 21,
                'reduced1' => 10,
                'reduced2' => 4,
            ],
            'FR' => [
                'standardRate' => 20,
                'reduced1' => 10,
                'reduced2' => 5.5,
            ],
            'HR' => [
                'standardRate' => 25,
                'reduced1' => 13,
                'reduced2' => 5,
            ],
            'IT' => [
                'standardRate' => 22,
                'reduced1' => 10,
                'reduced2' => 5,
            ],
            'CY' => [
                'standardRate' => 19,
                'reduced1' => 9,
                'reduced2' => 5,
            ],
            'LV' => [
                'standardRate' => 21,
                'reduced1' => 12,
                'reduced2' => 5,
            ],
            'LT' => [
                'standardRate' => 21,
                'reduced1' => 9,
                'reduced2' => 5,
            ],
            'LU' => [
                'standardRate' => 17,
                'reduced1' => 8,
                'reduced2' => 3,
            ],
            'HU' => [
                'standardRate' => 27,
                'reduced1' => 18,
                'reduced2' => 5,
            ],
            'MT' => [
                'standardRate' => 18,
                'reduced1' => 7,
                'reduced2' => 5,
            ],
            'NL' => [
                'standardRate' => 21,
                'reduced1' => 9,
            ],
            'AT' => [
                'standardRate' => 20,
                'reduced1' => 13,
                'reduced2' => 10,
            ],
            'PL' => [
                'standardRate' => 23,
                'reduced1' => 8,
                'reduced2' => 5,
            ],
            'PT' => [
                'standardRate' => 23,
                'reduced1' => 13,
                'reduced2' => 6,
            ],
            'RO' => [
                'standardRate' => 19,
                'reduced1' => 9,
                'reduced2' => 5,
            ],
            'SI' => [
                'standardRate' => 22,
                'reduced1' => 9.5,
            ],
            'SK' => [
                'standardRate' => 20,
                'reduced1' => 10,
            ],
            'FI' => [
                'standardRate' => 24,
                'reduced1' => 14,
                'reduced2' => 10,
            ],
            'SE' => [
                'standardRate' => 25,
                'reduced1' => 12,
                'reduced2' => 6,
            ],
            'GB' => [
                'standardRate' => 20,
                'reduced1' => 5,
            ],
        ];
    }
}
