<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\AffiliateTracking;

use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AffiliateTrackingListener implements EventSubscriberInterface
{
    public const AFFILIATE_CODE_KEY = OrderService::AFFILIATE_CODE_KEY;
    public const CAMPAIGN_CODE_KEY = OrderService::CAMPAIGN_CODE_KEY;

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

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        // Only process storefront routes
        if ($routeScope && !\in_array('storefront', $routeScope->getScopes(), true)) {
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
