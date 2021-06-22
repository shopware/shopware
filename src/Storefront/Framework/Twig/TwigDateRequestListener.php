<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Composer\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment;
use Twig\Extension\CoreExtension;

class TwigDateRequestListener implements EventSubscriberInterface
{
    public const TIMEZONE_COOKIE = 'timezone';

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public static function getSubscribedEvents()
    {
        return ['kernel.request' => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $timezone = (string) $event->getRequest()->cookies->get(self::TIMEZONE_COOKIE);

        if (!$timezone || !\in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        if (!$this->twig->hasExtension(CoreExtension::class)) {
            return;
        }
        /** @var CoreExtension $coreExtension */
        $coreExtension = $this->twig->getExtension(CoreExtension::class);
        $coreExtension->setTimezone($timezone);
    }
}
