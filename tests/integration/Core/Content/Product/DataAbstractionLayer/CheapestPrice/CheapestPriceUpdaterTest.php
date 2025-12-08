<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class CheapestPriceUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCheapestPriceFilteredBySalesChannelId(): void
    {
        $ids = new IdsCollection();

        $salesChannelId1 = TestDefaults::SALES_CHANNEL;

        $context = Context::createDefaultContext();

        $productBuilder = (new ProductBuilder($ids, 'testSalesChannelFilter_p1'))
            ->price(100.0)
            ->variant((new ProductBuilder($ids, 'testSalesChannelFilter_v1'))
                ->price(80.0)
                ->visibility($salesChannelId1)
                ->build())
            ->variant((new ProductBuilder($ids, 'testSalesChannelFilter_v2'))
                ->price(90.0)
                ->visibility()
                ->build());

        $productData = $productBuilder->build();
        $this->getContainer()->get('product.repository')->create([$productData], $context);

        $parentId = $productData['id'];
        $variantId1 = $productData['children'][0]['id'];

        $connection = $this->getContainer()->get(Connection::class);
        $quantitySelector = new CheapestPriceQuantitySelector();
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $updater = new CheapestPriceUpdater($connection, $quantitySelector, $eventDispatcher);
        $updater->update([$parentId], $context);

        $cheapestPriceRaw = $connection->fetchOne(
            'SELECT cheapest_price FROM product WHERE id = UNHEX(:id) AND version_id = UNHEX(:version)',
            [
                'id' => str_replace('-', '', $parentId),
                'version' => str_replace('-', '', $context->getVersionId()),
            ]
        );

        static::assertNotEmpty($cheapestPriceRaw, 'Cheapest price should be stored');

        $cheapestPrice = unserialize($cheapestPriceRaw);
        static::assertInstanceOf(CheapestPriceContainer::class, $cheapestPrice);

        $context = new Context(
            new SalesChannelApiSource($salesChannelId1)
        );

        $resolvedPrice = $cheapestPrice->resolve($context);
        static::assertNotNull($resolvedPrice);
        static::assertSame($variantId1, $resolvedPrice->getVariantId());

        $price = $resolvedPrice->getPrice()->first();
        static::assertNotNull($price);
        static::assertSame(80.0, $price->getGross());
    }
}
