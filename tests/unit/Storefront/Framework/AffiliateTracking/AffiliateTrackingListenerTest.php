<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\AffiliateTracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AffiliateTrackingListener::class)]
class AffiliateTrackingListenerTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            KernelEvents::CONTROLLER => [
                ['checkAffiliateTracking', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
        ], AffiliateTrackingListener::getSubscribedEvents());
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string|null> $expectedSession
     */
    #[DataProvider('queryProvider')]
    public function testStoresValidTrackingCodesInSession(array $query, array $expectedSession): void
    {
        $request = new Request(query: $query);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set(OrderService::CAMPAIGN_CODE_KEY, 'existing-campaign');

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertSame($expectedSession, [
            OrderService::AFFILIATE_CODE_KEY => $request->getSession()->get(OrderService::AFFILIATE_CODE_KEY),
            OrderService::CAMPAIGN_CODE_KEY => $request->getSession()->get(OrderService::CAMPAIGN_CODE_KEY),
        ]);
        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public static function queryProvider(): \Generator
    {
        yield 'both codes' => [
            'query' => ['affiliateCode' => 'affiliate', 'campaignCode' => 'campaign'],
            'expectedSession' => ['affiliateCode' => 'affiliate', 'campaignCode' => 'campaign'],
        ];

        yield 'only affiliate code keeps the existing campaign code' => [
            'query' => ['affiliateCode' => 'affiliate'],
            'expectedSession' => ['affiliateCode' => 'affiliate', 'campaignCode' => 'existing-campaign'],
        ];

        yield 'no tracking codes' => [
            'query' => ['foo' => 'bar'],
            'expectedSession' => ['affiliateCode' => null, 'campaignCode' => 'existing-campaign'],
        ];

        yield 'empty code' => [
            'query' => ['affiliateCode' => ''],
            'expectedSession' => ['affiliateCode' => null, 'campaignCode' => 'existing-campaign'],
        ];

        yield 'array code' => [
            'query' => ['affiliateCode' => ['affiliate']],
            'expectedSession' => ['affiliateCode' => null, 'campaignCode' => 'existing-campaign'],
        ];

        yield 'code longer than the database column' => [
            'query' => ['affiliateCode' => str_repeat('a', 256), 'campaignCode' => str_repeat('c', 255)],
            'expectedSession' => ['affiliateCode' => null, 'campaignCode' => str_repeat('c', 255)],
        ];
    }

    public function testDoesNotStoreTrackingCodesOutsideStorefrontScope(): void
    {
        $request = new Request(query: ['affiliateCode' => 'affiliate']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertFalse($request->getSession()->has(OrderService::AFFILIATE_CODE_KEY));
    }

    public function testIgnoresRequestWithoutSession(): void
    {
        $request = new Request(query: ['affiliateCode' => 'affiliate']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);

        (new AffiliateTrackingListener())->checkAffiliateTracking($this->createControllerEvent($request));

        static::assertFalse($request->hasSession(true));
    }

    private function createControllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
