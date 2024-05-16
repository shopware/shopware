<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<LineItem> $items
     */
    #[DataProvider('processorProvider')]
    public function testProcessor(array $items, CartPrice $cartPrice, ?Error $expectedError): void
    {
        $processor = $this->getContainer()->get(PromotionProcessor::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection($items));
        $cart->setPrice($cartPrice);

        $new = new Cart('after');
        $new->setLineItems(new LineItemCollection($items));
        $new->setPrice($cartPrice);

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection());

        $processor->process($data, $cart, $new, $context, new CartBehavior());

        if ($expectedError === null) {
            static::assertEquals(0, $new->getErrors()->count());
        } else {
            static::assertEquals(1, $new->getErrors()->filterInstance($expectedError::class)->count());
        }
    }

    public static function processorProvider(): \Generator
    {
        $context = Generator::createSalesChannelContext();
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);
        $context->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        yield 'Do not process discounts when cart is zero' => [
            [new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1)],
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new PromotionsOnCartPriceZeroError([]),
        ];
    }
}
