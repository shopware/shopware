<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Event\CartMergedSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartMergedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMergedHintIsAdded(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $subscriber = new CartMergedSubscriber($this->getContainer()->get('translator'), $requestStack);

        $subscriber->addCartMergedNoticeFlash($this->createMock(CartMergedEvent::class));

        static::assertNotEmpty($infoFlash = $session->getFlashBag()->get('info'));

        static::assertEquals('checkout.cart-merged-hint', $infoFlash[0]);
    }
}
