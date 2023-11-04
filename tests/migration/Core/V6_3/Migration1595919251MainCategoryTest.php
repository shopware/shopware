<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1595919251MainCategory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1595919251MainCategory
 */
class Migration1595919251MainCategoryTest extends TestCase
{
    public function testNoChanges(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $expectedSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `main_category`'))['Create Table'] ?? '';

        $migration = new Migration1595919251MainCategory();

        $migration->update($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `main_category`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');

        $migration->updateDestructive($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `main_category`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoSeoUrlTable(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $conn->executeStatement('DROP TABLE `main_category`');

        $migration = new Migration1595919251MainCategory();
        $migration->update($conn);
        $exists = $conn->fetchOne('SELECT COUNT(*) FROM `main_category`') !== false;

        static::assertTrue($exists);
    }
}
