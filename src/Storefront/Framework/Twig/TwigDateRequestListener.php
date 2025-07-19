<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
#[Package('framework')]
class TwigDateRequestListener
{
    final public const TIMEZONE_COOKIE = 'timezone';

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function __invoke(RequestEvent $event): void
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

        $coreExtension = $twig->getExtension(CoreExtension::class);
        $coreExtension->setTimezone($timezone);
    }
}
