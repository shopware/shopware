<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1723193659UpdateVatPatternForCountry extends MigrationStep
{
    private const VAT_PATTERNS = [
        'AT' => 'ATU\d{8}',
        'BE' => 'BE0\d{9}',
        'BG' => 'BG\d{9,10}',
        'CY' => 'CY\d{8}L',
        'CZ' => 'CZ\d{8,10}',
        'DK' => 'DK\d{8}',
        'EE' => 'EE\d{9}',
        'FI' => 'FI\d{8}',
        'FR' => 'FR[A-HJ-NP-Z0-9]{2}\d{9}',
        'DE' => 'DE\d{9}',
        'HU' => 'HU\d{8}',
        'IE' => 'IE\d{7}[A-WY][A-I]?|IE[0-9+][A-Z+][0-9]{5}[A-WY]',
        'IT' => 'IT\d{11}',
        'LV' => 'LV\d{11}',
        'LT' => 'LT\d{9,12}',
        'LU' => 'LU\d{8}',
        'MT' => 'MT\d{8}',
        'NL' => 'NL\d{9}B\d{2}',
        'PL' => 'PL\d{10}',
        'PT' => 'PT\d{9}',
        'RO' => 'RO\d{2,10}',
        'SK' => 'SK\d{10}',
        'SI' => 'SI\d{8}',
        'ES' => 'ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]',
        'SE' => 'SE\d{12}',
    ];

    public function getCreationTimestamp(): int
    {
        return 1723193659;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $update = new RetryableQuery(
            $connection,
            $connection->prepare('UPDATE country SET vat_id_pattern = :pattern WHERE iso = :iso')
        );

        foreach (self::VAT_PATTERNS as $key => $pattern) {
            $update->execute([
                'pattern' => $pattern,
                'iso' => $key,
            ]);
        }
    }
}
