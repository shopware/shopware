<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;

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

        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
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

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, $this->getContainer());

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

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, $this->getContainer());

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
}
