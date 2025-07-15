<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1748326970UpdateMailTemplatesForAccessibility;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1748326970UpdateMailTemplatesForAccessibility::class)]
class Migration1748326970UpdateMailTemplatesForAccessibilityTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationOfUnmodifiedTranslation(): void
    {
        $migration = new Migration1748326970UpdateMailTemplatesForAccessibility();
        $migration->update($this->connection);
        $migration->update($this->connection);

        // at least check that the migrations run without exceptions

        // there isn't much purpose in comparing the fixture contents with the DB,
        // because future migrations might change them again, so let's skip all that boilerplate here
    }
}
