<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderDetailPageLoadedEvent::class)]
class AccountOrderDetailPageLoadedEventTest extends TestCase
{
    public function testConstructorThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Class "Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedEvent" is deprecated and will be removed in v6.8.0.0.'
        ));

        new AccountOrderDetailPageLoadedEvent(new AccountOrderDetailPage(), Generator::generateSalesChannelContext(), new Request());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGettersReturnTheConstructorArgumentsWhenTheFeatureIsInactive(): void
    {
        $page = new AccountOrderDetailPage();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();

        $event = new AccountOrderDetailPageLoadedEvent($page, $salesChannelContext, $request);

        static::assertSame($page, $event->getPage());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame($request, $event->getRequest());
    }
}
