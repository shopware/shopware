<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794989867CorrectCountryStandardVatRates;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794989867CorrectCountryStandardVatRates::class)]
class Migration1794989867CorrectCountryStandardVatRatesTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * Mirrors the migration's own constant: [ISO code, rate shipped since 2020, rate in force].
     *
     * @var non-empty-list<array{0: string, 1: string, 2: string}>
     */
    private const CORRECTIONS = [
        ['EE', '20', '24'],
        ['FI', '24', '25.5'],
        ['RO', '19', '21'],
        ['SK', '20', '23'],
    ];

    private const STANDARD_RATE = 'Standard rate';

    private const REDUCED_RATE = 'Reduced rate';

    private const ENTIRE_COUNTRY = 'entire_country';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1794989867,
            (new Migration1794989867CorrectCountryStandardVatRates())->getCreationTimestamp()
        );
    }

    public function testCorrectsEveryOutdatedStandardRate(): void
    {
        $expectations = [];
        foreach (self::CORRECTIONS as [$iso, $previousRate, $currentRate]) {
            $expectations[] = [$iso, $this->createTaxRule($iso, $previousRate), $currentRate];
        }

        // Twice, because a migration has to be re-runnable.
        $migration = new Migration1794989867CorrectCountryStandardVatRates();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach ($expectations as [$iso, $ruleId, $currentRate]) {
            static::assertSame(
                (float) $currentRate,
                $this->fetchRate($ruleId),
                \sprintf('expected the standard rate of %s to be corrected', $iso)
            );
        }
    }

    /**
     * Even when the configured rate happens to equal the outdated default.
     */
    public function testKeepsARateTheShopOwnerEdited(): void
    {
        [$iso, $previousRate] = self::CORRECTIONS[0];
        $ruleId = $this->createTaxRule($iso, $previousRate, editedByShopOwner: true);

        (new Migration1794989867CorrectCountryStandardVatRates())->update($this->connection);

        static::assertSame((float) $previousRate, $this->fetchRate($ruleId));
    }

    /**
     * Same country, same rate value, different tax class. `updated_at IS NULL` alone would not save
     * the row, because a rule a shop owner created by hand has it NULL too.
     */
    public function testKeepsAMatchingRateInAReducedTaxClass(): void
    {
        [$iso, $previousRate] = self::CORRECTIONS[0];
        $ruleId = $this->createTaxRule($iso, $previousRate, taxClass: self::REDUCED_RATE);

        (new Migration1794989867CorrectCountryStandardVatRates())->update($this->connection);

        static::assertSame((float) $previousRate, $this->fetchRate($ruleId));
    }

    /**
     * Only the entire-country rules were written by Migration1589359936AddTaxCountryRules.
     */
    public function testKeepsAMatchingRateScopedToAPostalCode(): void
    {
        [$iso, $previousRate] = self::CORRECTIONS[0];
        $ruleId = $this->createTaxRule($iso, $previousRate, ruleType: 'zip_code');

        (new Migration1794989867CorrectCountryStandardVatRates())->update($this->connection);

        static::assertSame((float) $previousRate, $this->fetchRate($ruleId));
    }

    /**
     * Matching the rate shipped in 2020 is what protects a deliberately different value.
     */
    public function testKeepsARateThatIsNeitherTheOldNorTheNewDefault(): void
    {
        [$iso] = self::CORRECTIONS[0];
        $ownRate = '22';
        $ruleId = $this->createTaxRule($iso, $ownRate);

        (new Migration1794989867CorrectCountryStandardVatRates())->update($this->connection);

        static::assertSame((float) $ownRate, $this->fetchRate($ruleId));
    }

    public function testLeavesTheRatesOfOtherCountriesAlone(): void
    {
        [, $previousRate] = self::CORRECTIONS[0];

        $untouchedIso = $this->findCountryOutsideCorrections();
        $ruleId = $this->createTaxRule($untouchedIso, $previousRate);

        (new Migration1794989867CorrectCountryStandardVatRates())->update($this->connection);

        static::assertSame((float) $previousRate, $this->fetchRate($ruleId));
    }

    private function createTaxRule(
        string $iso,
        string $rate,
        bool $editedByShopOwner = false,
        string $taxClass = self::STANDARD_RATE,
        string $ruleType = self::ENTIRE_COUNTRY,
    ): string {
        $id = Uuid::randomBytes();

        $this->connection->insert('tax_rule', [
            'id' => $id,
            'tax_id' => $this->fetchTaxId($taxClass),
            'tax_rule_type_id' => $this->fetchTaxRuleTypeId($ruleType),
            'country_id' => $this->fetchCountryId($iso),
            'tax_rate' => $rate,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => $editedByShopOwner
                ? (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                : null,
        ]);

        return $id;
    }

    private function fetchRate(string $ruleId): float
    {
        return (float) $this->connection->fetchOne(
            'SELECT `tax_rate` FROM `tax_rule` WHERE `id` = :id',
            ['id' => $ruleId]
        );
    }

    private function fetchTaxId(string $name): string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `tax` WHERE `name` = :name LIMIT 1',
            ['name' => $name]
        );
        static::assertIsString($id, \sprintf('the "%s" tax class is missing', $name));

        return $id;
    }

    private function fetchTaxRuleTypeId(string $technicalName): string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `tax_rule_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );
        static::assertIsString($id, \sprintf('the "%s" tax rule type is missing', $technicalName));

        return $id;
    }

    private function fetchCountryId(string $iso): string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `country` WHERE `iso` = :iso LIMIT 1',
            ['iso' => $iso]
        );
        static::assertIsString($id, \sprintf('country "%s" is missing', $iso));

        return $id;
    }

    private function findCountryOutsideCorrections(): string
    {
        $isoCodes = array_column(self::CORRECTIONS, 0);

        $iso = $this->connection->fetchOne(
            'SELECT `iso` FROM `country` WHERE `iso` IS NOT NULL AND `iso` NOT IN (:isoCodes) LIMIT 1',
            ['isoCodes' => $isoCodes],
            ['isoCodes' => ArrayParameterType::STRING]
        );
        static::assertIsString($iso);

        return $iso;
    }
}
