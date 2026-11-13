<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Feature;
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

    private const DISCOUNT_ID = 'price-basis-discount';

    /**
     * @var array{id: string, taxRate: float, name: string}
     */
    private array $tax;

    private string $productId;

    protected function setUp(): void
    {
        $this->tax = ['id' => Uuid::randomHex(), 'taxRate' => 19.0, 'name' => 'price-basis-tax'];
        $this->productId = $this->createProduct(net: 10.00, gross: 99.99);
    }

    public function testNetBasisWithGrossDisplayDerivesTheCartLineItemGross(): void
    {
        $price = $this->calculateLineItemPrice($this->productId, CustomerGroupEntity::PRICE_BASIS_NET, displayGross: true);

        // the stored gross of 99.99 is ignored, 10.00 net is grossed up with the 19% rate instead
        static::assertSame(11.9, $price->getUnitPrice());
        static::assertSame(11.9, $price->getTotalPrice());
        static::assertSame(1.9, $price->getCalculatedTaxes()->getAmount());
    }

    public function testGrossBasisWithNetDisplayDerivesTheCartLineItemNet(): void
    {
        // the stored net of 3.00 is nowhere near the 10.00 that the fixed gross of 11.90 contains at 19%
        $productId = $this->createProduct(net: 3.00, gross: 11.90);

        $price = $this->calculateLineItemPrice($productId, CustomerGroupEntity::PRICE_BASIS_GROSS, displayGross: false);

        static::assertSame(10.0, $price->getUnitPrice());
        static::assertSame(1.9, $price->getCalculatedTaxes()->getAmount());

        // adding the derived tax back returns the customer to the stored gross, up to sub-cent rounding noise
        static::assertEqualsWithDelta(
            11.9,
            $price->getTotalPrice() + $price->getCalculatedTaxes()->getAmount(),
            0.005
        );
    }

    public function testWithoutAPriceBasisTheStoredGrossStaysAuthoritative(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $price = $this->calculateLineItemPrice($this->productId, null, displayGross: true);

        static::assertSame(99.99, $price->getUnitPrice());
        static::assertSame(15.96, $price->getCalculatedTaxes()->getAmount());
    }

    public function testGrossBasisWithNetDisplayChargesTheStoredGrossOfADiscountOverSeveralTaxRates(): void
    {
        $cart = $this->calculateMixedRateCartWithDiscount(
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            displayGross: false,
            standardRateProduct: ['net' => 1.0, 'gross' => 119.0],
            reducedRateProduct: ['net' => 1.0, 'gross' => 107.0],
            discount: new Price(Defaults::CURRENCY, -1.0, -11.9, false),
        );

        $discount = $cart->get(self::DISCOUNT_ID)?->getPrice();
        static::assertNotNull($discount);
        static::assertEqualsWithDelta(-11.9, $discount->getTotalPrice() + $discount->getCalculatedTaxes()->getAmount(), 0.000001);
        static::assertEqualsWithDelta(119.0 + 107.0 - 11.9, $cart->getPrice()->getTotalPrice(), 0.000001);
    }

    public function testNetBasisWithGrossDisplayKeepsTheStoredNetOfADiscountOverSeveralTaxRates(): void
    {
        $cart = $this->calculateMixedRateCartWithDiscount(
            CustomerGroupEntity::PRICE_BASIS_NET,
            displayGross: true,
            standardRateProduct: ['net' => 100.0, 'gross' => 1.0],
            reducedRateProduct: ['net' => 100.0, 'gross' => 1.0],
            discount: new Price(Defaults::CURRENCY, -10.0, -1.0, false),
        );

        $discount = $cart->get(self::DISCOUNT_ID)?->getPrice();
        static::assertNotNull($discount);
        static::assertEqualsWithDelta(-10.0, $discount->getTotalPrice() - $discount->getCalculatedTaxes()->getAmount(), 0.000001);
        static::assertEqualsWithDelta(100.0 + 100.0 - 10.0, $cart->getPrice()->getNetPrice(), 0.000001);
    }

    /**
     * @param array{id: string, taxRate: float, name: string}|null $tax
     */
    private function createProduct(float $net, float $gross, ?array $tax = null): string
    {
        $productId = Uuid::randomHex();

        static::getContainer()->get('product.repository')->create([[
            'id' => $productId,
            'name' => 'price basis product',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $net, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax ?? $this->tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ]], Context::createDefaultContext());

        return $productId;
    }

    private function calculateLineItemPrice(string $productId, ?string $priceBasis, bool $displayGross): CalculatedPrice
    {
        $context = $this->createContextForPriceBasis($priceBasis, $displayGross);

        $cart = new Cart('price-basis');
        $cart->add(
            (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        $calculated = static::getContainer()->get(Processor::class)->process($cart, $context, new CartBehavior());

        $item = $calculated->get($productId);
        static::assertNotNull($item);

        $price = $item->getPrice();
        static::assertNotNull($price);

        return $price;
    }

    /**
     * @param array{net: float, gross: float} $standardRateProduct
     * @param array{net: float, gross: float} $reducedRateProduct
     */
    private function calculateMixedRateCartWithDiscount(
        string $priceBasis,
        bool $displayGross,
        array $standardRateProduct,
        array $reducedRateProduct,
        Price $discount,
    ): Cart {
        $reducedTax = ['id' => Uuid::randomHex(), 'taxRate' => 7.0, 'name' => 'price-basis-reduced-tax'];

        $productIds = [
            $this->createProduct($standardRateProduct['net'], $standardRateProduct['gross']),
            $this->createProduct($reducedRateProduct['net'], $reducedRateProduct['gross'], $reducedTax),
        ];

        $context = $this->createContextForPriceBasis($priceBasis, $displayGross);
        $this->addTaxDataToSalesChannel($context, $reducedTax);

        $cart = new Cart('price-basis');
        foreach ($productIds as $productId) {
            $cart->add(
                (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1))
                    ->setStackable(true)
                    ->setRemovable(true)
            );
        }

        $cart->add(
            (new LineItem(self::DISCOUNT_ID, LineItem::DISCOUNT_LINE_ITEM))
                ->setLabel('price basis discount')
                ->setGood(false)
                ->setRemovable(true)
                ->setPriceDefinition(new CurrencyPriceDefinition(new PriceCollection([$discount])))
        );

        return static::getContainer()->get(Processor::class)->process($cart, $context, new CartBehavior());
    }

    private function createContextForPriceBasis(?string $priceBasis, bool $displayGross): SalesChannelContext
    {
        $customerGroupId = Uuid::randomHex();

        static::getContainer()->get('customer_group.repository')->create([[
            'id' => $customerGroupId,
            'name' => 'price basis group',
            'displayGross' => $displayGross,
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
