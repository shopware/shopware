<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\AffiliateTracking;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AffiliateTrackingListener implements EventSubscriberInterface
{
    /**
     * @var Session
     */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [['checkAffiliateTracking', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST]],
        ];
    }

    public function checkAffiliateTracking(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        // Only process storefront routes
        if ($routeScope && !in_array('storefront', $routeScope->getScopes(), true)) {
            return;
        }

        $session = $request->getSession();
        $affiliateCode = $request->query->get('affiliateCode');
        $campaignCode = $request->query->get('campaignCode');

        if ($affiliateCode && $campaignCode) {
            $session->set('affiliateCode', $affiliateCode);
            $session->set('campaignCode', $campaignCode);
        }
    }
}
