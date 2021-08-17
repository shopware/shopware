<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1629204538AddTimeZoneField;

class Migration1629204538AddTimeZoneFieldTest extends TestCase
{
    use KernelTestBehaviour;

    public function testFieldExists(): void
    {
        $c = $this->getContainer()->get(Connection::class);

        $columns = array_keys($c->getSchemaManager()->listTableColumns('user'));

        if (\in_array('time_zone', $columns, true)) {
            $c->executeStatement('ALTER TABLE `user` DROP `time_zone`;');
        }

        $m = new Migration1629204538AddTimeZoneField();
        $m->update($c);

        static::assertArrayHasKey('time_zone', $c->getSchemaManager()->listTableColumns('user'));
    }
}
