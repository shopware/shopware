<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;

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
        ?SystemConfigService $systemConfigService = null
    ): AvailableCombinationLoader {
        return new AvailableCombinationLoader(
            $this->getProductRepository(),
            $stockStorage ?? static::createStub(AbstractStockStorage::class),
            $systemConfigService ?? static::createStub(SystemConfigService::class),
        );
    }

    /**
     * @return StaticSalesChannelRepository<SalesChannelProductCollection>
     */
    private function getProductRepository(): StaticSalesChannelRepository
    {
        $variants = [
            $this->createVariant('product-1', ['green', 'red'], true, false),
            $this->createVariant('product-2', ['green'], false, true),
        ];

        /** @var StaticSalesChannelRepository<SalesChannelProductCollection> $repository */
        $repository = new StaticSalesChannelRepository([
            static function (Criteria $criteria) use ($variants): array {
                static::assertSame(['optionIds', 'productNumber', 'available', 'isCloseout'], $criteria->getFields());

                return $variants;
            },
        ]);

        return $repository;
    }

    /**
     * @param list<string> $optionIds
     */
    private function createVariant(string $id, array $optionIds, bool $available, bool $isCloseout): PartialEntity
    {
        $variant = new PartialEntity([
            'optionIds' => $optionIds,
            'productNumber' => $id,
            'available' => $available,
            'isCloseout' => $isCloseout,
        ]);
        $variant->setUniqueIdentifier($id);

        return $variant;
    }
}
