<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1752662784ChangeCountryNameTuerkiye;

/**
 * @internal
 */
#[CoversClass(Migration1752662784ChangeCountryNameTuerkiye::class)]
class Migration1752662784ChangeCountryNameTuerkiyeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUpdate(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('UPDATE country_translation SET name = ? WHERE name = ?', [
            'Turkey',
            'Türkiye',
        ]);

        $connection->executeStatement('UPDATE country_translation SET name = ? WHERE name = ?', [
            'Macedonia (the former Yugoslav Republic of)',
            'North Macedonia',
        ]);

        $migration = new Migration1752662784ChangeCountryNameTuerkiye();
        $migration->update($connection);

        $turkey = $connection->fetchOne('SELECT name FROM country_translation WHERE name = ?', ['Türkiye']);
        static::assertSame('Türkiye', $turkey);

        $macedonia = $connection->fetchOne('SELECT name FROM country_translation WHERE name = ?', ['North Macedonia']);
        static::assertSame('North Macedonia', $macedonia);
    }
}
