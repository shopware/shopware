<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Doctrine\FakeResultFactory;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(FakeResultFactory::class)]
class FakeResultFactoryTest extends TestCase
{
    public function testCreateResult(): void
    {
        $data = [
            ['id' => 1, 'name' => 'foo', 'description' => 'bar description'],
            ['id' => 2, 'name' => 'bar', 'description' => 'foo description'],
        ];

        $connection = $this->createMock(Connection::class);

        $result = FakeResultFactory::createResult($data, $connection);

        static::assertSame(2, $result->rowCount());
        static::assertSame(3, $result->columnCount());
        static::assertSame('description', $result->getColumnName(2));
        static::assertSame($data, $result->fetchAllAssociative());
    }
}
