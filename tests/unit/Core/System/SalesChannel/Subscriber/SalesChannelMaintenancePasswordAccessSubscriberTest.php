<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelMaintenancePasswordAccessSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelMaintenancePasswordAccessSubscriber::class)]
class SalesChannelMaintenancePasswordAccessSubscriberTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                MaintenanceModeRequestEvent::class => 'allowGrantedClient',
                KernelEvents::RESPONSE => ['rememberGrant', 0],
            ],
            SalesChannelMaintenancePasswordAccessSubscriber::getSubscribedEvents()
        );
    }

    public function testAllowsClientWithValidGrant(): void
    {
        $event = new MaintenanceModeRequestEvent(self::createRequest('hash:generation'), [], false);

        (new SalesChannelMaintenancePasswordAccessSubscriber())->allowGrantedClient($event);

        static::assertTrue($event->isClientAllowed());
    }

    /**
     * @return iterable<string, array{0: Request}>
     */
    public static function requestsWithoutValidGrant(): iterable
    {
        $staleGrant = self::createRequest('hash:previous-generation');

        $noSession = self::createRequest(null);

        $maintenanceInactive = self::createRequest('hash:generation');
        $maintenanceInactive->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, false);

        $noPassword = self::createRequest('hash:generation');
        $noPassword->attributes->remove(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD);

        yield 'missing grant' => [self::createRequest(null, withSession: true)];
        yield 'grant of a previous password generation' => [$staleGrant];
        yield 'no session' => [$noSession];
        yield 'maintenance inactive' => [$maintenanceInactive];
        yield 'no maintenance password' => [$noPassword];
    }

    #[DataProvider('requestsWithoutValidGrant')]
    public function testDoesNotAllowClientWithoutValidGrant(Request $request): void
    {
        $event = new MaintenanceModeRequestEvent($request, [], false);

        (new SalesChannelMaintenancePasswordAccessSubscriber())->allowGrantedClient($event);

        static::assertFalse($event->isClientAllowed());
    }

    public function testKeepsAlreadyAllowedClientAllowed(): void
    {
        $event = new MaintenanceModeRequestEvent(self::createRequest(null), ['127.0.0.1'], true);

        (new SalesChannelMaintenancePasswordAccessSubscriber())->allowGrantedClient($event);

        static::assertTrue($event->isClientAllowed());
    }

    public function testReusesTheGrantRememberedBeforeTheSessionWasSaved(): void
    {
        $request = self::createRequest('hash:generation');
        $subscriber = new SalesChannelMaintenancePasswordAccessSubscriber();

        $subscriber->rememberGrant(new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        ));
        $request->getSession()->remove(SalesChannelMaintenancePasswordAccessSubscriber::SESSION_KEY_PREFIX . self::SALES_CHANNEL_ID);

        $event = new MaintenanceModeRequestEvent($request, [], false);
        $subscriber->allowGrantedClient($event);

        static::assertTrue($event->isClientAllowed());
    }

    private static function createRequest(?string $grant, bool $withSession = false): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, self::SALES_CHANNEL_ID);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, true);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, 'hash');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD_GENERATION, 'generation');

        if ($grant === null && !$withSession) {
            return $request;
        }

        $session = new Session(new MockArraySessionStorage());
        if ($grant !== null) {
            $session->set(SalesChannelMaintenancePasswordAccessSubscriber::SESSION_KEY_PREFIX . self::SALES_CHANNEL_ID, $grant);
        }
        $request->setSession($session);

        return $request;
    }
}
