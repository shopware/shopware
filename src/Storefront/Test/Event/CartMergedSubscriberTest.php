<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Event\CartMergedSubscriber;

class CartMergedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMergedHintIsAdded(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $this->getContainer()->get(CartMergedSubscriber::class)->addCartMergedNoticeFlash($this->createMock(CartMergedEvent::class));

        $flashBag = $this->getContainer()->get('session')->getFlashBag();

        static::assertNotEmpty($infoFlash = $flashBag->get('info'));
        static::assertEquals($this->getContainer()->get('translator')->trans('checkout.cart-merged-hint'), $infoFlash[0]);
    }
}
