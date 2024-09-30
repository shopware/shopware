<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AvailableCombinationLoader::class)]
class AvailableCombinationLoaderTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getAvailableCombinationLoader()->getDecorated();
    }

    public function testLoadCombinationsReturnsAvailableCombinationResult(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::createSalesChannelContext($context);
        $loader = $this->getAvailableCombinationLoader();
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        $combinations = $result->getCombinations();
        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
            'a6a23a74867cad90ee0c788a48944911' => [
                'green',
            ],
        ], $combinations);
    }

    public function testLoadCombinationsReturnsAvailableCombinationResultWithAvailabilityFromStockStorage(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::createSalesChannelContext($context);

        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $stockStorage->expects(static::once())
            ->method('load')
            ->willReturn(new StockDataCollection([
                new StockData('product-1', 10, false),
            ]));

        $loader = $this->getAvailableCombinationLoader($stockStorage);
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        $combinations = $result->getCombinations();
        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
            'a6a23a74867cad90ee0c788a48944911' => [
                'green',
            ],
        ], $combinations);

        static::assertFalse($result->isAvailable(['green', 'red']));
        static::assertFalse($result->isAvailable(['green']));
    }

    private function getAvailableCombinationLoader(?AbstractStockStorage $stockStorage = null): AvailableCombinationLoader
    {
        $connection = $this->getMockedConnection();

        return new AvailableCombinationLoader($connection, $stockStorage ?? $this->createMock(AbstractStockStorage::class));
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
