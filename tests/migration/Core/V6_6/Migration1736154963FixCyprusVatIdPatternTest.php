<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1736154963FixCyprusVatIdPattern;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1736154963FixCyprusVatIdPattern::class)]
class Migration1736154963FixCyprusVatIdPatternTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $this->connection->update('country', ['vat_id_pattern' => 'CY\d{8}L'], ['iso' => 'CY']);

        $migration = new Migration1736154963FixCyprusVatIdPattern();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $result = $this->connection
            ->executeQuery('SELECT `vat_id_pattern` FROM country WHERE vat_id_pattern = :pat', ['pat' => 'CY\d{8}[A-Z]'])
            ->fetchAssociative();

        static::assertNotEmpty($result);
        static::assertSame('CY\d{8}[A-Z]', $result['vat_id_pattern']);
    }
}
