<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1629204538AddTimeZoneField;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1629204538AddTimeZoneField
 */
class Migration1629204538AddTimeZoneFieldTest extends TestCase
{
    public function testFieldExists(): void
    {
        $c = KernelLifecycleManager::getConnection();

        $columns = array_keys($c->getSchemaManager()->listTableColumns('user'));

        if (\in_array('time_zone', $columns, true)) {
            $c->executeStatement('ALTER TABLE `user` DROP `time_zone`;');
        }

        $m = new Migration1629204538AddTimeZoneField();
        $m->update($c);

        static::assertArrayHasKey('time_zone', $c->getSchemaManager()->listTableColumns('user'));
    }
}
