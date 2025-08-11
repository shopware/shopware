<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1754892246FixWordingMistakeInEmailTemplates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1754892246FixWordingMistakeInEmailTemplates::class)]
class Migration1754892246FixWordingMistakeInEmailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationOfUnmodifiedTranslation(): void
    {
        $migration = new Migration1754892246FixWordingMistakeInEmailTemplates();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }
}
