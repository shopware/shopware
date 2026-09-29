<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\PriceModifier;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationCollector;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationProcessor;
use Shopware\Core\Checkout\PriceModifier\PriceCollectorInterface;
use Shopware\Core\Checkout\PriceModifier\PriceModifier;
use Shopware\Core\Checkout\PriceModifier\PriceModifierAbsolutePriceDefinition;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCollection;
use Shopware\Core\Checkout\PriceModifier\PriceModifierResult;
use Shopware\Core\Checkout\PriceModifier\PriceProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * Proves the substance of F1/G5 for PriceCollectorInterface/PriceProcessorInterface: the shipped
 * OrderPriceModificationCollector/OrderPriceModificationProcessor pair is not the only thing the
 * shopware.cart.price_collector/shopware.cart.price_processor tagged_iterator plumbing can carry --
 * a second, independent registrant coexists with it and contributes its own data/modifiers.
 *
 * @internal
 */
#[Package('checkout')]
class PriceModifierExtensionPointTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $factory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->context = $factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testASecondPriceCollectorAndProcessorCoexistWithTheGenericPair(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE));

        $fixtureCollector = new class implements PriceCollectorInterface {
            public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
            {
                $data->set('fixture-price-collector-ran', true);
            }
        };

        $fixtureProcessor = new class implements PriceProcessorInterface {
            public function process(
                CartDataCollection $data,
                Cart $original,
                Cart $toCalculate,
                PriceCollection $prices,
                PriceCollection $shippingCosts,
                PriceCollection $additionalCosts,
                float $taxExemptAdjustment,
                SalesChannelContext $context,
                CartBehavior $behavior
            ): PriceModifierResult {
                $modifier = new PriceModifier(
                    label: 'Fixture fee',
                    price: new PriceModifierCalculatedPrice(1.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    priceDefinition: new PriceModifierAbsolutePriceDefinition(1.0, null, false),
                );

                return new PriceModifierResult($prices, $shippingCosts, new PriceCollection(), 0.0, new PriceModifierCollection([$modifier]));
            }
        };

        $processor = new Processor(
            static::getContainer()->get(Validator::class),
            static::getContainer()->get(AmountCalculator::class),
            static::getContainer()->get(CashRounding::class),
            static::getContainer()->get(TransactionProcessor::class),
            [],
            [],
            static::getContainer()->get(ScriptExecutor::class),
            [$fixtureCollector, static::getContainer()->get(OrderPriceModificationCollector::class)],
            [$fixtureProcessor, static::getContainer()->get(OrderPriceModificationProcessor::class)],
        );

        $result = $processor->process($cart, $this->context, new CartBehavior());

        // Both registered price_collectors ran, not just the shipped one -- the same
        // CartDataCollection bag carries both their writes.
        static::assertTrue($result->getData()->has('fixture-price-collector-ran'));
        static::assertTrue($result->getData()->has(OrderPriceModificationCollector::DATA_KEY));

        // OrderPriceModificationProcessor is a no-op here (no order exists for this cart), so the
        // fixture processor's modifier is the only one -- proving a second, independent registrant's
        // contribution survives sitting alongside the generic pair, not just compiling next to it.
        static::assertCount(1, $result->getPriceModifiers());
        $modifier = $result->getPriceModifiers()->first();
        static::assertNotNull($modifier);
        static::assertSame('Fixture fee', $modifier->getLabel());
    }
}
