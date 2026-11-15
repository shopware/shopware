<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
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

        static::assertIsString($cheapestPriceRaw, 'Cheapest price should be stored');
        static::assertNotSame('', $cheapestPriceRaw, 'Cheapest price should be stored');

        $cheapestPrice = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $cheapestPriceRaw);

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

    public function testStandaloneProductThatBecomesAVariantInheritsTheParentCheapestPrice(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('product.repository');

        $repository->create([
            (new ProductBuilder($ids, 'parent'))->price(20.0)->visibility()->build(),
            (new ProductBuilder($ids, 'formerStandalone'))->price(20.0, listPriceGross: 40.0)->visibility()->build(),
        ], $context);

        static::assertNotNull($this->fetchOwnCheapestPrice($ids->get('formerStandalone')), 'The standalone product stores its own cheapest price');

        $repository->update([['id' => $ids->get('formerStandalone'), 'parentId' => $ids->get('parent')]], $context);
        $repository->update([['id' => $ids->get('formerStandalone'), 'price' => [[
            'currencyId' => Defaults::CURRENCY,
            'gross' => 20.0,
            'net' => 20.0 / 115 * 100,
            'linked' => false,
        ]]]], $context);

        static::assertNull($this->fetchOwnCheapestPrice($ids->get('formerStandalone')), 'The variant must inherit the cheapest price of its parent');

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $variant = $this->getContainer()->get('sales_channel.product.repository')
            ->search(new Criteria([$ids->get('formerStandalone')]), $salesChannelContext)
            ->getEntities()
            ->first();

        static::assertInstanceOf(SalesChannelProductEntity::class, $variant);
        static::assertNull($variant->getCalculatedPrice()->getListPrice());
        static::assertNull($variant->getCalculatedCheapestPrice()->getListPrice(), 'The list price of the former standalone product must not survive');
    }

    public function testUpdateRemovesAStaleCheapestPriceFromAVariant(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $this->getContainer()->get('product.repository')->create([
            (new ProductBuilder($ids, 'parent'))
                ->price(20.0)
                ->variant((new ProductBuilder($ids, 'variant'))->price(20.0)->build())
                ->build(),
        ], $context);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE product SET cheapest_price = :price WHERE id = :id',
            ['price' => $this->fetchOwnCheapestPrice($ids->get('parent')), 'id' => Uuid::fromHexToBytes($ids->get('variant'))]
        );
        static::assertNotNull($this->fetchOwnCheapestPrice($ids->get('variant')));

        $updater = new CheapestPriceUpdater($connection, new CheapestPriceQuantitySelector(), $this->getContainer()->get('event_dispatcher'));
        $updater->update([$ids->get('parent')], $context);

        static::assertNull($this->fetchOwnCheapestPrice($ids->get('variant')));
        static::assertNotNull($this->fetchOwnCheapestPrice($ids->get('parent')));
    }

    private function fetchOwnCheapestPrice(string $productId): ?string
    {
        $value = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT cheapest_price FROM product WHERE id = :id AND version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );

        static::assertNotFalse($value, 'Product row must exist');

        return $value === null ? null : (string) $value;
    }
}
