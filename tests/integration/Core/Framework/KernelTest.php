<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUTCIsAlwaysSetToDatabase(): void
    {
        $c = static::getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }
}
