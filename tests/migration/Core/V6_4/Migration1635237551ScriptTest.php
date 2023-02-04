<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1635237551Script;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1635237551Script
 */
class Migration1635237551ScriptTest extends TestCase
{
    public function testTablesIsPresent(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS `script`;');

        $migration = new Migration1635237551Script();
        $migration->update($connection);

        $appScriptColumns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM script'), 'Field');

        static::assertContains('id', $appScriptColumns);
        static::assertContains('script', $appScriptColumns);
        static::assertContains('hook', $appScriptColumns);
        static::assertContains('name', $appScriptColumns);
        static::assertContains('active', $appScriptColumns);
        static::assertContains('app_id', $appScriptColumns);
        static::assertContains('created_at', $appScriptColumns);
        static::assertContains('updated_at', $appScriptColumns);
    }
}
