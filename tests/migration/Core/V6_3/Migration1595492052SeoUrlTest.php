<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1595492052SeoUrl;
use Shopware\Core\Migration\V6_4\Migration1643366069AddSeoUrlUpdaterIndex;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1595492052SeoUrl
 */
class Migration1595492052SeoUrlTest extends TestCase
{
    public function testNoChanges(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $expectedSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url`'))['Create Table'];

        $migration = new Migration1595492052SeoUrl();

        $migration->update($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');

        $migration->updateDestructive($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoSeoUrlTable(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $conn->executeStatement('DROP TABLE `seo_url`');

        $migration = new Migration1595492052SeoUrl();
        $migration->update($conn);

        $m = new Migration1643366069AddSeoUrlUpdaterIndex();
        $m->update($conn);
        $exists = $conn->fetchOne('SELECT COUNT(*) FROM `seo_url`') !== false;

        static::assertTrue($exists);
    }
}
