<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use PHPUnit\Framework\Assert;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\System\Salutation\SalesChannel\SalutationRouteTest
 */
#[Package('framework')]
class StoreApiSessionListener implements EventSubscriberInterface
{
    private const SESSION_INITIALIZED = '_test_store_api_session_initialized';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['recordSessionState', 0],
            KernelEvents::TERMINATE => 'assertSessionIsNotInitialized',
        ];
    }

    public function recordSessionState(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $routeScopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\is_array($routeScopes) || !\in_array(StoreApiRouteScope::ID, $routeScopes, true)) {
            return;
        }

        $request->attributes->set(self::SESSION_INITIALIZED, $request->hasSession(true));
    }

    public function assertSessionIsNotInitialized(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(self::SESSION_INITIALIZED)) {
            return;
        }

        Assert::assertFalse(
            $request->attributes->getBoolean(self::SESSION_INITIALIZED),
            \sprintf('Store API request "%s" initialized a session.', $request->getPathInfo())
        );
    }
}
