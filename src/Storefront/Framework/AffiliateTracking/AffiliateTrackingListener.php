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
 * Stores the affiliate and campaign code of the query in the session.
 * Pages ignore both parameters in the HTTP cache key,
 * so for a cached page the `CartWidget` plugin forwards them with its request.
 *
 * @internal
 */
#[Package('checkout')]
class AffiliateTrackingListener implements EventSubscriberInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `OrderService::AFFILIATE_CODE_KEY` instead
     */
    final public const AFFILIATE_CODE_KEY = OrderService::AFFILIATE_CODE_KEY;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `OrderService::CAMPAIGN_CODE_KEY` instead
     */
    final public const CAMPAIGN_CODE_KEY = OrderService::CAMPAIGN_CODE_KEY;

    /**
     * Length of the `affiliate_code` and `campaign_code` columns of orders and customers
     */
    private const MAX_CODE_LENGTH = 255;

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

        if (!\in_array(StorefrontRouteScope::ID, $scopes, true)) {
            return;
        }

        $codes = [];
        foreach ([OrderService::AFFILIATE_CODE_KEY, OrderService::CAMPAIGN_CODE_KEY] as $key) {
            $code = $request->query->all()[$key] ?? null;

            if (\is_string($code) && $code !== '' && mb_strlen($code) <= self::MAX_CODE_LENGTH) {
                $codes[$key] = $code;
            }
        }

        if ($codes === []) {
            return;
        }

        /** @phpstan-ignore shopware.unsafeRequestHasSession (using $skipIfUninitialized = false as session will be started intentionally later; this can take the PHP session lock and is limited to affiliate tracking storing codes in the storefront session.) */
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        foreach ($codes as $key => $code) {
            $session->set($key, $code);
        }
    }
}
