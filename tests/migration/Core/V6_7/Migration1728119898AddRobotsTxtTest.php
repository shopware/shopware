<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1728119898AddRobotsTxt;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1728119898AddRobotsTxt::class)]
class Migration1728119898AddRobotsTxtTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $connection->delete('system_config', ['configuration_key' => 'core.basicInformation.robotsRules']);

        $migration = new Migration1728119898AddRobotsTxt();
        $migration->update($connection);
        $migration->update($connection);

        $robotsRules = $connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key',
            ['key' => 'core.basicInformation.robotsRules']
        );

        static::assertIsString($robotsRules);

        $robotsRules = json_decode($robotsRules, true);
        static::assertIsArray($robotsRules);
        static::assertArrayHasKey('_value', $robotsRules);
        static::assertSame(
            <<<'TXT'
                Disallow: /account/
                Disallow: /checkout/
                Disallow: /widgets/
                Allow: /widgets/cms/
                Allow: /widgets/menu/offcanvas
                TXT,
            $robotsRules['_value']
        );
    }
}
