<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionHandlingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = static::getContainer()->get(CartService::class);

        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * This test verifies that our promotions are not added
     * if our cart is empty and has no products yet.
     */
    #[Group('promotions')]
    public function testPromotionNotAddedWithoutProduct(): void
    {
        $productId = Uuid::randomHex();
        $code = 'BF19';

        $this->createTestFixtureProduct($productId, 119, 19, static::getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add our promotion to our cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertCount(0, $cart->getLineItems());
    }

    /**
     * This test verifies that our promotions are correctly
     * removed when also removing the last product
     */
    #[Group('promotions')]
    public function testPromotionsRemovedWithProduct(): void
    {
        $productId = Uuid::randomHex();
        $code = 'BF19';

        $this->createTestFixtureProduct($productId, 119, 19, static::getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add our promotion to our cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        $ids = array_keys($cart->getLineItems()->getElements());
        static::assertArrayHasKey(0, $ids);

        // remove our first item (product)
        $cart = $this->cartService->remove($cart, $ids[0], $this->context);

        static::assertCount(0, $cart->getLineItems());
    }

    /**
     * This test verifies that a promotion code on a cart that holds
     * only custom items reports that there is nothing to discount,
     * even though the cart total is not zero.
     */
    #[Group('promotions')]
    public function testPromotionCodeOnCartWithOnlyCustomItemsReportsMissingProducts(): void
    {
        $code = 'BF19';

        $this->createTestFixtureAbsolutePromotion(Uuid::randomHex(), $code, 10, static::getContainer());

        $customItem = (new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE))
            ->setLabel('Custom item')
            ->setPriceDefinition(new QuantityPriceDefinition(50, new TaxRuleCollection([new TaxRule(19)]), 1));

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);
        $cart = $this->cartService->add($cart, $customItem, $this->context);

        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertSame(50.0, $cart->getPrice()->getPositionPrice());
        static::assertCount(0, $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE));

        $error = $cart->getErrors()->filterInstance(PromotionsOnCartPriceZeroError::class)->first();
        static::assertInstanceOf(PromotionsOnCartPriceZeroError::class, $error);
        static::assertSame(['Black Friday'], array_values($error->getPromotions()));
    }
}
