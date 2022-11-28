<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageOrderCriteriaEvent;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FinishPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresAOrderId(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectParamMissingException('orderId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testMissingOrderThrows(): void
    {
        $request = new Request([], [], ['orderId' => 'foo']);
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(OrderException::class);

        $this->getPageLoader()->load($request, $context);
    }

    public function testFinishPageLoading(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request = new Request([], [], ['orderId' => $orderId]);
        $eventWasThrown = false;
        $criteria = new Criteria([$orderId]);

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            CheckoutFinishPageOrderCriteriaEvent::class,
            static function (CheckoutFinishPageOrderCriteriaEvent $event) use ($criteria, &$eventWasThrown): void {
                static::assertSame($criteria->getIds(), $event->getCriteria()->getIds());
                $eventWasThrown = true;
            }
        );

        /** @var CheckoutFinishPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutFinishPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutFinishPage::class, $page);
        static::assertSame(13.04, $page->getOrder()->getPrice()->getNetPrice());
        self::assertPageEvent(CheckoutFinishPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertTrue($eventWasThrown);

        $this->resetEventDispatcher();
    }

    /**
     * @return CheckoutFinishPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(CheckoutFinishPageLoader::class);
    }
}
