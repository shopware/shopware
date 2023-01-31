<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1602822727AddVatHandlingIntoCountryTable extends MigrationStep
{
    /**
     * @var array<string, string>
     */
    private array $countryIds;

    public function getCreationTimestamp(): int
    {
        return 1602822727;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `country`
            ADD COLUMN `company_tax_free` TINYINT (1) NOT NULL DEFAULT 0 AFTER `force_state_in_registration`,
            ADD COLUMN `check_vat_id_pattern` TINYINT (1) NOT NULL DEFAULT 0 AFTER `company_tax_free`,
            ADD COLUMN `vat_id_pattern` VARCHAR (255) NULL AFTER `check_vat_id_pattern`;
        ');

        $this->addCountryVatPattern($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addCountryVatPattern(Connection $connection): void
    {
        $this->fetchCountryIds($connection);

        foreach ($this->getCountryVatPattern() as $isoCode => $countryVatPattern) {
            if (!\array_key_exists($isoCode, $this->countryIds)) {
                // country was deleted by shop owner
                continue;
            }

            $connection->update('country', ['vat_id_pattern' => $countryVatPattern], ['id' => $this->countryIds[$isoCode]]);
        }
    }

    private function fetchCountryIds(Connection $connection): void
    {
        /** @var list<array{id: string, iso: string}> $countries */
        $countries = $connection->executeQuery('SELECT `id`, `iso` FROM `country`')->fetchAllAssociative();

        foreach ($countries as $country) {
            $this->countryIds[$country['iso']] = $country['id'];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getCountryVatPattern(): array
    {
        return [
            'AT' => '(AT)?U[0-9]{8}',
            'BE' => '(BE)?0[0-9]{9}',
            'BG' => '(BG)?[0-9]{9,10}',
            'CY' => '(CY)?[0-9]{8}L',
            'CZ' => '(CZ)?[0-9]{8,10}',
            'DE' => '(DE)?[0-9]{9}',
            'DK' => '(DK)?[0-9]{8}',
            'EE' => '(EE)?[0-9]{9}',
            'GR' => '(EL|GR)?[0-9]{9}',
            'ES' => '(ES)?[0-9A-Z][0-9]{7}[0-9A-Z]',
            'FI' => '(FI)?[0-9]{8}',
            'FR' => '(FR)?[0-9A-Z]{2}[0-9]{9}',
            'GB' => '(GB)?([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3})',
            'HU' => '(HU)?[0-9]{8}',
            'IE' => '(IE)?[0-9]S[0-9]{5}L',
            'IT' => '(IT)?[0-9]{11}',
            'LT' => '(LT)?([0-9]{9}|[0-9]{12})',
            'LU' => '(LU)?[0-9]{8}',
            'LV' => '(LV)?[0-9]{11}',
            'MT' => '(MT)?[0-9]{8}',
            'NL' => '(NL)?[0-9]{9}B[0-9]{2}',
            'PL' => '(PL)?[0-9]{10}',
            'PT' => '(PT)?[0-9]{9}',
            'RO' => '(RO)?[0-9]{2,10}',
            'SE' => '(SE)?[0-9]{12}',
            'SI' => '(SI)?[0-9]{8}',
            'SK' => '(SK)?[0-9]{10}',
        ];
    }
}
