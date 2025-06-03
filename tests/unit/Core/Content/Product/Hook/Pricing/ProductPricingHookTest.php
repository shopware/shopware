<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Hook\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\PriceFactoryFactory;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Content\Product\Hook\Pricing\ProductPricingHook;
use Shopware\Core\Content\Product\Hook\Pricing\ProductProxy;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPricingHook::class)]
class ProductPricingHookTest extends TestCase
{
    public function testGetProducts(): void
    {
        $salesChannelContext = static::createMock(SalesChannelContext::class);

        $productProxy = new ProductProxy(
            (new SalesChannelProductEntity())->assign(['name' => 'foo']),
            $salesChannelContext,
            $this->createMock(ScriptPriceStubs::class)
        );
        $productPricingHook = new ProductPricingHook([$productProxy], $salesChannelContext);

        static::assertSame([$productProxy], $productPricingHook->getProducts());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                PriceFactoryFactory::class,
                SystemConfigFacadeHookFactory::class,
                SalesChannelRepositoryFacadeHookFactory::class,
            ],
            ProductPricingHook::getServiceIds()
        );
    }

    public function testGetName(): void
    {
        $productPricingHook = new ProductPricingHook([], static::createMock(SalesChannelContext::class));

        static::assertSame('product-pricing', $productPricingHook->getName());
    }

    public function testGetSalesChannelContext(): void
    {
        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $productPricingHook = new ProductPricingHook([], $salesChannelContext);

        static::assertSame($salesChannelContext, $productPricingHook->getSalesChannelContext());
    }
}
