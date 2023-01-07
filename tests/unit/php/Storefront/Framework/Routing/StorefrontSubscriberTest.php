<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 * @covers \Shopware\Storefront\Framework\Routing\StorefrontSubscriber
 */
class StorefrontSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['showHtmlExceptionResponse', -100],
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            CustomerLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            CustomerLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            BeforeSendResponseEvent::class => [
                ['replaceCsrfToken'],
                ['setCanonicalUrl'],
            ],
            StorefrontRenderEvent::class => [
                ['addHreflang'],
                ['addShopIdParameter'],
                ['addIconSetConfig'],
            ],
            SalesChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];

        if (Feature::isActive('v6.5.0.0')) {
            $expected = [
                KernelEvents::REQUEST => [
                    ['startSession', 40],
                    ['maintenanceResolver'],
                ],
                KernelEvents::EXCEPTION => [
                    ['customerNotLoggedInHandler'],
                    ['maintenanceResolver'],
                ],
                KernelEvents::CONTROLLER => [
                    ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
                ],
                CustomerLoginEvent::class => [
                    'updateSessionAfterLogin',
                ],
                CustomerLogoutEvent::class => [
                    'updateSessionAfterLogout',
                ],
                BeforeSendResponseEvent::class => [
                    ['setCanonicalUrl'],
                ],
                StorefrontRenderEvent::class => [
                    ['addHreflang'],
                    ['addShopIdParameter'],
                    ['addIconSetConfig'],
                ],
                SalesChannelContextResolvedEvent::class => [
                    ['replaceContextToken'],
                ],
            ];
        }

        static::assertSame($expected, StorefrontSubscriber::getSubscribedEvents());
    }
}
