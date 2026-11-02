<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RowLoader::class)]
class RowLoaderTest extends TestCase
{
    public function testNothingIsLoadedWithoutPrimaryKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('createQueryBuilder');

        static::assertSame([], (new RowLoader($connection))->load('product', [], ['id']));
    }
}
