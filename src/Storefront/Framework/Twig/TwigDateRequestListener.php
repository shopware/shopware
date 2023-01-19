<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Composer\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Extension\CoreExtension;

/**
 * @package storefront
 */
class TwigDateRequestListener implements EventSubscriberInterface
{
    public const TIMEZONE_COOKIE = 'timezone';

    private ContainerInterface $container;

    /**
     * @internal
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $timezone = (string) $event->getRequest()->cookies->get(self::TIMEZONE_COOKIE);

        if (!$timezone || !\in_array($timezone, timezone_identifiers_list(), true) || $timezone === 'UTC') {
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
