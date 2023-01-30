<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader
 */
class AvailableCombinationLoaderTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getAvailableCombinationLoader()->getDecorated();
    }

    public function testLoadReturnsAvailableCombinationResult(): void
    {
        $loader = $this->getAvailableCombinationLoader();
        $result = $loader->load(
            Uuid::randomHex(),
            Context::createDefaultContext(),
            TestDefaults::SALES_CHANNEL
        );

        static::assertInstanceOf(AvailableCombinationResult::class, $result);

        $combinations = $result->getCombinations();
        static::assertSame([
            'a3f67ea263a4f2f5cf456e16de744b4b' => [
                'green',
                'red',
            ],
            'b6073234fc601007b541885dd70491f1' => [
                'green',
            ],
        ], $combinations);
    }

    private function getAvailableCombinationLoader(): AvailableCombinationLoader
    {
        $connection = $this->getMockedConnection();

        return new AvailableCombinationLoader($connection);
    }

    private function getMockedConnection(): Connection
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            [
                'id' => 'product-1',
                'available' => true,
                'options' => json_encode([
                    'green',
                    'red',
                ]),
            ],
            [
                'id' => 'product-2',
                'available' => false,
                'options' => json_encode([
                    'green',
                ]),
            ],
            [
                'id' => 'invalid',
                'available' => false,
                'options' => '{ bar: "baz" }',
            ],
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
