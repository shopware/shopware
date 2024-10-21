<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Composer\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Extension\CoreExtension;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - This event listener will be internal
 */
#[Package('storefront')]
class TwigDateRequestListener implements EventSubscriberInterface
{
    final public const TIMEZONE_COOKIE = 'timezone';

    /**
     * @internal
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - return type will be array
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!\in_array(StorefrontRouteScope::ID, $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return;
        }

        $timezone = (string) $request->cookies->get(self::TIMEZONE_COOKIE);

        if ($timezone === 'UTC' || !$timezone || !\in_array($timezone, timezone_identifiers_list(), true)) {
            // Default will be UTC @see https://symfony.com/doc/current/reference/configuration/twig.html#timezone
            return;
        }

        $twig = $this->container->get('twig');

        if (!$twig->hasExtension(CoreExtension::class)) {
            return;
        }

        /** @var CoreExtension $coreExtension */
        $coreExtension = $twig->getExtension(CoreExtension::class);
        $coreExtension->setTimezone($timezone);
    }
}
