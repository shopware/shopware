<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerGroupPriceBasisTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var array{id: string, taxRate: float, name: string}
     */
    private array $tax;

    private string $productId;

    protected function setUp(): void
    {
        $this->tax = ['id' => Uuid::randomHex(), 'taxRate' => 19.0, 'name' => 'price-basis-tax'];
        $this->productId = Uuid::randomHex();

        static::getContainer()->get('product.repository')->create([[
            'id' => $this->productId,
            'name' => 'price basis product',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 99.99, 'net' => 10.00, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $this->tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ]], Context::createDefaultContext());
    }

    public function testNetBasisWithGrossDisplayDerivesTheCartLineItemGross(): void
    {
        $price = $this->calculateLineItemPrice(CustomerGroupEntity::PRICE_BASIS_NET);

        // the stored gross of 99.99 is ignored, 10.00 net is grossed up with the 19% rate instead
        static::assertSame(11.9, $price->getUnitPrice());
        static::assertSame(11.9, $price->getTotalPrice());
        static::assertSame(1.9, $price->getCalculatedTaxes()->getAmount());
    }

    public function testWithoutAPriceBasisTheStoredGrossStaysAuthoritative(): void
    {
        $price = $this->calculateLineItemPrice(null);

        static::assertSame(99.99, $price->getUnitPrice());
        static::assertSame(15.96, $price->getCalculatedTaxes()->getAmount());
    }

    private function calculateLineItemPrice(?string $priceBasis): CalculatedPrice
    {
        $context = $this->createContextForPriceBasis($priceBasis);

        $cart = new Cart('price-basis');
        $cart->add(
            (new LineItem($this->productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $this->productId, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        $calculated = static::getContainer()->get(Processor::class)->process($cart, $context, new CartBehavior());

        $item = $calculated->get($this->productId);
        static::assertNotNull($item);

        $price = $item->getPrice();
        static::assertNotNull($price);

        return $price;
    }

    private function createContextForPriceBasis(?string $priceBasis): SalesChannelContext
    {
        $customerGroupId = Uuid::randomHex();

        static::getContainer()->get('customer_group.repository')->create([[
            'id' => $customerGroupId,
            'name' => 'price basis group',
            'displayGross' => true,
            'priceBasis' => $priceBasis,
        ]], Context::createDefaultContext());

        static::getContainer()->get('sales_channel.repository')->update([[
            'id' => TestDefaults::SALES_CHANNEL,
            'customerGroupId' => $customerGroupId,
        ]], Context::createDefaultContext());

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->addTaxDataToSalesChannel($context, $this->tax);

        return $context;
    }
}
