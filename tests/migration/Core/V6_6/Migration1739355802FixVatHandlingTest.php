<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1739355802FixVatHandling;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1739355802FixVatHandling::class)]
class Migration1739355802FixVatHandlingTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateAddsVatPatterns(): void
    {
        $this->removeVatPatterns();

        $migration = new Migration1739355802FixVatHandling();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $patterns = $this->fetchVatPatterns();

        $expectedPatterns = [
            'BE' => 'BE\d{10}',
            'GR' => '(EL|GR)\d{9}',
            'HR' => 'HR\d{11}',
            'IE' => 'IE(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))', // pre and post 2013 pattern
            'LT' => 'LT(\d{12}|\d{9})',
            'RO' => 'RO(?!0)\d{1,10}',
        ];

        foreach ($expectedPatterns as $iso => $pattern) {
            static::assertArrayHasKey($iso, $patterns);
            static::assertSame($pattern, $patterns[$iso]);
        }
    }

    private function removeVatPatterns(): void
    {
        $this->connection->executeStatement(
            'UPDATE `country` SET `vat_id_pattern` = NULL'
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function fetchVatPatterns(): array
    {
        $result = $this->connection->executeQuery(
            'SELECT `iso`, `vat_id_pattern` FROM `country`'
        )->fetchAllAssociative();

        $patterns = [];
        foreach ($result as $row) {
            $patterns[$row['iso']] = $row['vat_id_pattern'];
        }

        return $patterns;
    }
}
