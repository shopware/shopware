<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1704267596UpdateBelgianVatIdPattern;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1704267596UpdateBelgianVatIdPattern::class)]
class Migration1704267596UpdateBelgianVatIdPatternTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $this->connection->update('country', ['vat_id_pattern' => '(BE)?0[0-9]{9}'], ['iso' => 'BE']);

        $migration = new Migration1704267596UpdateBelgianVatIdPattern();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $result = $this->connection->executeQuery('SELECT `vat_id_pattern` FROM country WHERE vat_id_pattern = \'(BE)?(0|1)[0-9]{9}\'')->fetchAssociative();
        static::assertNotEmpty($result);
        static::assertSame('(BE)?(0|1)[0-9]{9}', $result['vat_id_pattern']);
    }
}
