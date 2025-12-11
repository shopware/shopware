<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

class CacheCookieEventSubscriber implements EventSubscriberInterface, ResetInterface
{
    private static bool $flashbagWasFilled = false;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            HttpCacheCookieEvent::class => 'passCacheForFlashMessages',
        ];
    }

    public function passCacheForFlashMessages(HttpCacheCookieEvent $cookieEvent): void
    {
        // if flashbag is filled still when the response is sent, we need to pass the cache also for further requests
        if ($this->flashBagFilledForCurrentSession()) {
            $cookieEvent->passCache = true;
            return;
        }

        // if flashbag was filled before, but is empty now that means that the response contains flash messages
        // therefore we cannot store the response in the cache
        // however in general the cache should be used for the next requests
        if (self::$flashbagWasFilled) {
            $cookieEvent->doNotStore = true;
        }
    }

    public function onKernelRequest(): void
    {
        self::$flashbagWasFilled = $this->flashBagFilledForCurrentSession();
    }

    private function flashBagFilledForCurrentSession(): bool
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return false;
        }

        return $session->getFlashBag()->keys() !== [];
    }

    public function reset(): void
    {
        self::$flashbagWasFilled = false;
    }
}