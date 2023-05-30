<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCartEventTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    /**
     * If we add more than two promotions we could encounter an infinite recursion if a promotion is added
     * via the cart service in the StorefrontCartSubscriber.
     * For good measure, we add more than three promotions to the cart in this case.
     * Our main test case is to create additional dispatch listener that make sure our event is
     * only called once for every discount +1 for the actual product.
     * We also must not call a line item removed event.
     *
     * @group promotions
     */
    public function testAvoidInfiniteLoopEventsWithLotsOfPromotions(): void
    {
        $productId = Uuid::randomHex();

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->getContext());

        $codes = [100, 1, 42, 13, 19];
        $this->createBulkPromotions($codes);

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $addListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $addListener->expects(static::exactly(1 + \count($codes)))->method('__invoke');
        $this->addEventListener($dispatcher, BeforeLineItemAddedEvent::class, $addListener);

        $cart = $this->cartService->getCart($this->getContext()->getToken(), $this->getContext());

        // add all our prepared test fixtures
        // and promotions to our current cart.
        foreach ($codes as $code) {
            $cart = $this->addPromotionCode((string) $code, $cart, $this->cartService, $this->getContext());
        }

        // now add our product which should trigger our event
        $this->addProduct($productId, 1, $cart, $this->cartService, $this->getContext());
    }

    /**
     * This test verifies that we only fire our remove item
     * once, even though we have lots of promotions in our cart.
     *
     * @group promotions
     */
    public function testAvoidInfiniteLoopEventsWhenRemovingLotsOfPromotions(): void
    {
        $productId = Uuid::randomHex();

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->getContext());

        $codes = [100, 1, 42, 13, 19];
        $this->createBulkPromotions($codes);

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $removeListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $removeListener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, BeforeLineItemRemovedEvent::class, $removeListener);

        $cart = $this->cartService->getCart($this->getContext()->getToken(), $this->getContext());

        foreach ($codes as $code) {
            $cart = $this->addPromotionCode((string) $code, $cart, $this->cartService, $this->getContext());
        }

        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->getContext());

        // now remove our cart, make sure our remove event is only called once
        $this->cartService->remove($cart, $productId, $this->getContext());
    }

    /**
     * @param list<float> $codes
     */
    private function createBulkPromotions(array $codes): void
    {
        foreach ($codes as $percentage) {
            $this->createTestFixturePercentagePromotion(
                Uuid::randomHex(),
                (string) $percentage,
                $percentage,
                null,
                $this->getContainer()
            );
        }
    }
}
