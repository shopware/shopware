<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\Event\AvailableCombinationQueryEvent;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
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
        $salesChanelContext = Generator::generateSalesChannelContext($context);
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

    public function testLoadCombinationsDispatchesQueryExtensionEventBeforeQueryExecution(): void
    {
        $context = Generator::generateSalesChannelContext(Context::createDefaultContext());
        $productId = Uuid::randomHex();

        $dispatchedEvent = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(AvailableCombinationQueryEvent::class, static function (AvailableCombinationQueryEvent $event) use (&$dispatchedEvent): void {
            $dispatchedEvent = $event;
        });

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(static function () use (&$dispatchedEvent, $queryBuilder, $result): Result {
                static::assertInstanceOf(AvailableCombinationQueryEvent::class, $dispatchedEvent);
                static::assertSame($queryBuilder, $dispatchedEvent->getQueryBuilder());

                return $result;
            });

        $this->getAvailableCombinationLoader(eventDispatcher: $eventDispatcher, queryBuilder: $queryBuilder)
            ->loadCombinations($productId, $context);

        static::assertInstanceOf(AvailableCombinationQueryEvent::class, $dispatchedEvent);
        static::assertSame($productId, $dispatchedEvent->getProductId());
        static::assertSame($context, $dispatchedEvent->getSalesChannelContext());
        static::assertSame($context->getContext(), $dispatchedEvent->getContext());
    }

    public function testLoadCombinationsReturnsAvailableCombinationResultWithAvailabilityFromStockStorage(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::generateSalesChannelContext($context);

        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())
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

    public function testLoadCombinationsHidesCloseoutVariantsWhenConfigured(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::generateSalesChannelContext($context);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getBool')
            ->willReturn(true);

        $loader = $this->getAvailableCombinationLoader(
            systemConfigService: $systemConfigService
        );
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
        ], $result->getCombinations());
    }

    private function getAvailableCombinationLoader(
        ?AbstractStockStorage $stockStorage = null,
        ?SystemConfigService $systemConfigService = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?QueryBuilder $queryBuilder = null,
    ): AvailableCombinationLoader {
        $connection = $this->getMockedConnection($queryBuilder);

        return new AvailableCombinationLoader(
            $connection,
            $stockStorage ?? static::createStub(AbstractStockStorage::class),
            $systemConfigService ?? static::createStub(SystemConfigService::class),
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    private function getMockedConnection(?QueryBuilder $queryBuilder = null): Connection
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            [
                'id' => 'product-1',
                'available' => true,
                'isCloseout' => false,
                'options' => json_encode([
                    'green',
                    'red',
                ]),
            ],
            [
                'id' => 'product-2',
                'available' => false,
                'isCloseout' => true,
                'options' => json_encode([
                    'green',
                ]),
            ],
            [
                'id' => 'invalid',
                'available' => false,
                'isCloseout' => false,
                'options' => '{ bar: "baz" }',
            ],
        ]);

        if ($queryBuilder === null) {
            $queryBuilder = static::createStub(QueryBuilder::class);
            $queryBuilder->method('executeQuery')->willReturn($result);
        }

        $connection = static::createStub(Connection::class);
        $parentResult = static::createStub(Result::class);
        $parentResult->method('fetchAssociative')->willReturn(false);
        $parentQueryBuilder = static::createStub(QueryBuilder::class);
        $parentQueryBuilder->method('executeQuery')->willReturn($parentResult);
        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls($parentQueryBuilder, $queryBuilder);

        return $connection;
    }
}
