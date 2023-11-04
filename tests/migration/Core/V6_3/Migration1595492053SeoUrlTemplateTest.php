<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1595492053SeoUrlTemplate;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1595492053SeoUrlTemplate
 */
class Migration1595492053SeoUrlTemplateTest extends TestCase
{
    public function testNoChanges(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $expectedSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url_template`'))['Create Table'] ?? '';

        $migration = new Migration1595492053SeoUrlTemplate();

        $migration->update($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url_template`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');

        $migration->updateDestructive($conn);
        $actualSchema = ((array) $conn->fetchAssociative('SHOW CREATE TABLE `seo_url_template`'))['Create Table'] ?? '';
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoSeoUrlTable(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $conn->executeStatement('DROP TABLE `seo_url_template`');

        $migration = new Migration1595492053SeoUrlTemplate();
        $migration->update($conn);
        $exists = $conn->fetchOne('SELECT COUNT(*) FROM `seo_url_template`') !== false;

        static::assertTrue($exists);
    }
}
