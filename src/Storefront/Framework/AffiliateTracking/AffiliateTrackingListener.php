<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\AffiliateTracking;

use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('storefront')]
class AffiliateTrackingListener implements EventSubscriberInterface
{
    final public const AFFILIATE_CODE_KEY = OrderService::AFFILIATE_CODE_KEY;
    final public const CAMPAIGN_CODE_KEY = OrderService::CAMPAIGN_CODE_KEY;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['checkAffiliateTracking', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
        ];
    }

    public function checkAffiliateTracking(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        // Only process storefront routes
        if (!\in_array(StorefrontRouteScope::ID, $scopes, true)) {
            return;
        }

        $session = $request->getSession();
        $affiliateCode = $request->query->get(self::AFFILIATE_CODE_KEY);
        $campaignCode = $request->query->get(self::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $session->set(self::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode) {
            $session->set(self::CAMPAIGN_CODE_KEY, $campaignCode);
        }
    }
}
