<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerLogoutSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLogoutEvent::class => ['onCustomerLogout', -10000],
        ];
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        $event->getSalesChannelContext()->setImitatingUserId(null);

        $mainRequest = $this->requestStack->getMainRequest();

        // Only clear an initialized storefront session. Store API requests use their context token directly.
        if (!$mainRequest?->hasSession(true)) {
            return;
        }

        $mainRequest->getSession()->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
    }
}
