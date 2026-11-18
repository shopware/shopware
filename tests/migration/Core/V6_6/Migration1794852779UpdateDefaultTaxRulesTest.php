<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1794852779UpdateDefaultTaxRules;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794852779UpdateDefaultTaxRules::class)]
class Migration1794852779UpdateDefaultTaxRulesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1794852779UpdateDefaultTaxRules $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1794852779UpdateDefaultTaxRules();
        $this->resetToRatesOfShopware63();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794852779, $this->migration->getCreationTimestamp());
    }

    public function testUpdatesOutdatedDefaultRules(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertEquals(['Standard rate' => 21, 'Reduced rate' => 12, 'Reduced rate 2' => 12], $this->fetchRates('CZ'));
        static::assertEquals(['Standard rate' => 24, 'Reduced rate' => 9], $this->fetchRates('EE'));
        static::assertEquals(['Standard rate' => 25.5, 'Reduced rate' => 13.5, 'Reduced rate 2' => 10], $this->fetchRates('FI'));
        static::assertEquals(['Standard rate' => 21, 'Reduced rate' => 5, 'Reduced rate 2' => 5], $this->fetchRates('LT'));
        static::assertEquals(['Standard rate' => 21, 'Reduced rate' => 11, 'Reduced rate 2' => 11], $this->fetchRates('RO'));
        static::assertEquals(['Standard rate' => 22, 'Reduced rate' => 9.5, 'Reduced rate 2' => 5], $this->fetchRates('SI'));
        static::assertEquals(['Standard rate' => 23, 'Reduced rate' => 5, 'Reduced rate 2' => 5], $this->fetchRates('SK'));
        static::assertSame(1, $this->countRules('SI', 'Reduced rate 2'));
        static::assertSame(1, $this->countRules('SK', 'Reduced rate 2'));
    }

    public function testKeepsRulesChangedByTheMerchant(): void
    {
        $this->setRate('FI', 'Standard rate', rate: 24, updatedAt: '2024-01-01 00:00:00.000');

        $this->migration->update($this->connection);

        static::assertEquals(24, $this->fetchRates('FI')['Standard rate']);
    }

    public function testKeepsRulesOfOtherTaxGroups(): void
    {
        $this->connection->insert('tax', [
            'id' => Uuid::randomBytes(),
            'tax_rate' => 7,
            'name' => 'Books',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->insertRule('SK', 'Books', 10);

        $this->migration->update($this->connection);

        static::assertEquals(10, $this->fetchRates('SK')['Books']);
    }

    public function testKeepsExistingReducedRate2Rule(): void
    {
        $this->insertRule('SK', 'Reduced rate 2', 0);

        $this->migration->update($this->connection);

        static::assertEquals(0, $this->fetchRates('SK')['Reduced rate 2']);
        static::assertSame(1, $this->countRules('SK', 'Reduced rate 2'));
    }

    public function testAddsNoReducedRate2RuleToCountriesWithoutDefaultRules(): void
    {
        $this->connection->executeStatement(
            'DELETE `tax_rule` FROM `tax_rule` INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id` WHERE `country`.`iso` = :iso',
            ['iso' => 'SI']
        );

        $this->migration->update($this->connection);

        static::assertSame([], $this->fetchRates('SI'));
    }

    private function resetToRatesOfShopware63(): void
    {
        $this->setRate('CZ', 'Reduced rate', rate: 15);
        $this->setRate('CZ', 'Reduced rate 2', rate: 10);
        $this->setRate('EE', 'Standard rate', rate: 20);
        $this->setRate('FI', 'Standard rate', rate: 24);
        $this->setRate('FI', 'Reduced rate', rate: 14);
        $this->setRate('LT', 'Reduced rate', rate: 9);
        $this->setRate('RO', 'Standard rate', rate: 19);
        $this->setRate('RO', 'Reduced rate', rate: 9);
        $this->setRate('RO', 'Reduced rate 2', rate: 5);
        $this->setRate('SK', 'Standard rate', rate: 20);
        $this->setRate('SK', 'Reduced rate', rate: 10);

        $this->connection->executeStatement(
            'DELETE `tax_rule` FROM `tax_rule`
                INNER JOIN `tax` ON `tax`.`id` = `tax_rule`.`tax_id`
                INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id`
            WHERE `country`.`iso` IN (:isos) AND `tax`.`name` = :taxName',
            ['isos' => ['SI', 'SK'], 'taxName' => 'Reduced rate 2'],
            ['isos' => ArrayParameterType::STRING]
        );
    }

    private function setRate(string $iso, string $taxName, float $rate, ?string $updatedAt = null): void
    {
        $this->connection->executeStatement(
            'UPDATE `tax_rule`
                INNER JOIN `tax` ON `tax`.`id` = `tax_rule`.`tax_id`
                INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id`
            SET `tax_rule`.`tax_rate` = :rate, `tax_rule`.`updated_at` = :updatedAt
            WHERE `country`.`iso` = :iso AND `tax`.`name` = :taxName',
            ['rate' => $rate, 'updatedAt' => $updatedAt, 'iso' => $iso, 'taxName' => $taxName]
        );
    }

    private function insertRule(string $iso, string $taxName, float $rate): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `tax_rule` (`id`, `tax_id`, `tax_rule_type_id`, `country_id`, `tax_rate`, `created_at`)
            SELECT :id, `tax`.`id`, `tax_rule_type`.`id`, `country`.`id`, :rate, :createdAt
            FROM `tax`, `country`, `tax_rule_type`
            WHERE `tax`.`name` = :taxName AND `country`.`iso` = :iso AND `tax_rule_type`.`technical_name` = :ruleType',
            [
                'id' => Uuid::randomBytes(),
                'rate' => $rate,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'taxName' => $taxName,
                'iso' => $iso,
                'ruleType' => 'entire_country',
            ]
        );
    }

    /**
     * @return array<string, float>
     */
    private function fetchRates(string $iso): array
    {
        /** @var array<string, string> $rates */
        $rates = $this->connection->fetchAllKeyValue(
            'SELECT `tax`.`name`, `tax_rule`.`tax_rate`
            FROM `tax_rule`
                INNER JOIN `tax` ON `tax`.`id` = `tax_rule`.`tax_id`
                INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id`
            WHERE `country`.`iso` = :iso',
            ['iso' => $iso]
        );

        return array_map(floatval(...), $rates);
    }

    private function countRules(string $iso, string $taxName): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
            FROM `tax_rule`
                INNER JOIN `tax` ON `tax`.`id` = `tax_rule`.`tax_id`
                INNER JOIN `country` ON `country`.`id` = `tax_rule`.`country_id`
            WHERE `country`.`iso` = :iso AND `tax`.`name` = :taxName',
            ['iso' => $iso, 'taxName' => $taxName]
        );
    }
}
