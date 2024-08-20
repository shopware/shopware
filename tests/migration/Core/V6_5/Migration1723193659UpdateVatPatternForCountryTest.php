<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1723193659UpdateVatPatternForCountry;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1723193659UpdateVatPatternForCountry
 */
#[Package('core')]
class Migration1723193659UpdateVatPatternForCountryTest extends TestCase
{
    private const OLD_PATTERNS = [
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

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->prepare($this->connection);
    }

    public function testMigration(): void
    {
        $patterns = $this->connection->fetchAllKeyValue(
            'SELECT iso, vat_id_pattern FROM country WHERE iso IN (:iso)',
            ['iso' => array_keys(self::OLD_PATTERNS)],
            ['iso' => ArrayParameterType::STRING]
        );

        foreach (self::OLD_PATTERNS as $key => $pattern) {
            static::assertSame($pattern, $patterns[$key]);
        }

        $migration = new Migration1723193659UpdateVatPatternForCountry();
        $migration->update($this->connection);

        $patterns = $this->connection->fetchAllKeyValue(
            'SELECT iso, vat_id_pattern FROM country WHERE iso IN (:iso)',
            ['iso' => array_keys(self::OLD_PATTERNS)],
            ['iso' => ArrayParameterType::STRING]
        );

        static::assertSame('ATU\d{8}', $patterns['AT']);
        static::assertSame('BE0\d{9}', $patterns['BE']);
        static::assertSame('BG\d{9,10}', $patterns['BG']);
        static::assertSame('CY\d{8}L', $patterns['CY']);
        static::assertSame('CZ\d{8,10}', $patterns['CZ']);
        static::assertSame('DK\d{8}', $patterns['DK']);
        static::assertSame('EE\d{9}', $patterns['EE']);
        static::assertSame('FI\d{8}', $patterns['FI']);
        static::assertSame('FR[A-HJ-NP-Z0-9]{2}\d{9}', $patterns['FR']);
        static::assertSame('DE\d{9}', $patterns['DE']);
        static::assertSame('HU\d{8}', $patterns['HU']);
        static::assertSame('IE\d{7}[A-WY][A-I]?|IE[0-9+][A-Z+][0-9]{5}[A-WY]', $patterns['IE']);
        static::assertSame('IT\d{11}', $patterns['IT']);
        static::assertSame('LV\d{11}', $patterns['LV']);
        static::assertSame('LT\d{9,12}', $patterns['LT']);
        static::assertSame('LU\d{8}', $patterns['LU']);
        static::assertSame('MT\d{8}', $patterns['MT']);
        static::assertSame('NL\d{9}B\d{2}', $patterns['NL']);
        static::assertSame('PL\d{10}', $patterns['PL']);
        static::assertSame('PT\d{9}', $patterns['PT']);
        static::assertSame('RO\d{2,10}', $patterns['RO']);
        static::assertSame('SK\d{10}', $patterns['SK']);
        static::assertSame('SI\d{8}', $patterns['SI']);
        static::assertSame('ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]', $patterns['ES']);
        static::assertSame('SE\d{12}', $patterns['SE']);
    }

    private function prepare(Connection $connection): void
    {
        $update = new RetryableQuery(
            $connection,
            $connection->prepare('UPDATE country SET vat_id_pattern = :pattern WHERE iso = :iso')
        );

        foreach (self::OLD_PATTERNS as $key => $pattern) {
            $update->execute([
                'pattern' => $pattern,
                'iso' => $key,
            ]);
        }
    }
}
