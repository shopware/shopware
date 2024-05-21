<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(StoreApiCustomFieldMapper::class)]
class StoreApiCustomFieldMapperTest extends TestCase
{
    public function testMapping(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['entity_name' => 'customer', 'name' => 'allowed']]);

        $mapper = new StoreApiCustomFieldMapper($connection);
        static::assertSame(['allowed' => 'yes'], $mapper->map('customer', new RequestDataBag(['bla' => 'foo', 'allowed' => 'yes'])));
    }

    public function testInternalStorageWorks(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $mapper = new StoreApiCustomFieldMapper($connection);
        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));
        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));

        $mapper->reset();

        static::assertSame([], $mapper->map('customer', new RequestDataBag(['bla' => 'foo'])));
    }
}
