<?php

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\V6_4\Migration1661771388FixDefaultCountryStatesTranslationAreMissing;
use Shopware\Core\Migration\V6_4\Migration1663322402AddMarginResponsiveColumnToCmsBlock;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1663322402AddMarginResponsiveColumnToCmsBlock
 */
class Migration1663322402AddMarginResponsiveColumnToCmsBlockTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1663322402AddMarginResponsiveColumnToCmsBlock $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1663322402AddMarginResponsiveColumnToCmsBlock();
        $this->prepare();
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);
        $columns = $this->connection->fetchAll('SHOW COLUMNS FROM `cms_block`');
        $columns = array_column($columns, 'Field');
        static::assertContains('margin_responsive', $columns);
    }
}
