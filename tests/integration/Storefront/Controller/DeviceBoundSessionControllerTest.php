<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\DeviceBoundSessionController;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionService;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionSubscriber;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Shopware\Storefront\Test\Framework\DeviceBoundSession\TestDeviceKey;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Walks through registration, refresh and enforcement of a device bound storefront session.
 *
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private TestDeviceKey $deviceKey;

    /**
     * @var array<string, callable>
     */
    private array $listeners = [];

    protected function setUp(): void
    {
        $this->deviceKey = new TestDeviceKey();
        $this->enableDeviceBoundSessions();
    }

    protected function tearDown(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        foreach ($this->listeners as $event => $listener) {
            $dispatcher->removeListener($event, $listener);
        }
    }

    public function testLoggedInCustomerCanBindTheSessionAndKeepsUsingIt(): void
    {
        $browser = $this->login();
        $cookieName = $this->register($browser);

        static::assertNotNull($browser->getCookieJar()->get($cookieName));
        $this->assertLoggedIn($browser);
    }

    public function testSessionCookieWithoutBoundCookieIsLoggedOut(): void
    {
        $browser = $this->login();
        $cookieName = $this->register($browser);

        $browser->getCookieJar()->expire($cookieName);

        $this->assertLoggedOut($browser);
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM device_bound_session'));
    }

    public function testStolenSessionCookieIsLoggedOutForEveryone(): void
    {
        $browser = $this->login();
        $this->register($browser);
        $sessionCookie = $this->sessionCookie($browser);

        $attacker = KernelLifecycleManager::createBrowser($this->getKernel());
        $attacker->followRedirects(false);
        $attacker->getCookieJar()->set($sessionCookie);

        $this->assertLoggedOut($attacker);
        $this->assertLoggedOut($browser);
    }

    public function testBrowserRefreshesTheBoundCookieWithTheDeviceKey(): void
    {
        $browser = $this->login();
        $cookieName = $this->register($browser);
        $sessionId = $this->sessionIdentifier($browser);
        $previousValue = $browser->getCookieJar()->get($cookieName)?->getValue();

        $browser->request('POST', $this->url('/device-bound-session/refresh'), server: ['HTTP_SEC_SECURE_SESSION_ID' => '"' . $sessionId . '"']);
        static::assertSame(403, $browser->getResponse()->getStatusCode());

        $challengeHeader = (string) $browser->getResponse()->headers->get('Secure-Session-Challenge');
        if (!preg_match('/^"(\w+)";id="' . $sessionId . '"$/', $challengeHeader, $matches)) {
            static::fail('Unexpected challenge header: ' . $challengeHeader);
        }

        $browser->request('POST', $this->url('/device-bound-session/refresh'), server: [
            'HTTP_SEC_SECURE_SESSION_ID' => '"' . $sessionId . '"',
            'HTTP_SECURE_SESSION_RESPONSE' => '"' . $this->deviceKey->signRefresh($matches[1]) . '"',
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $newValue = $browser->getCookieJar()->get($cookieName)?->getValue();
        static::assertNotNull($newValue);
        static::assertNotSame($previousValue, $newValue);
        $this->assertLoggedIn($browser);
    }

    public function testRegistrationIsNotOfferedAgainOnceBound(): void
    {
        $browser = $this->login();
        $this->register($browser);

        $browser->request('GET', $this->url('/account'));

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertFalse($browser->getResponse()->headers->has('Secure-Session-Registration'));
    }

    public function testLogoutRemovesTheBinding(): void
    {
        $browser = $this->login();
        $this->register($browser);

        $browser->request('GET', $this->url('/account/logout'));

        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM device_bound_session'));
    }

    /**
     * The container parameter cannot be changed at runtime, so the enabled state is simulated by
     * registering enabled listener and controller instances.
     */
    private function enableDeviceBoundSessions(): void
    {
        $container = static::getContainer();
        $service = $container->get(DeviceBoundSessionService::class);

        $subscriber = new DeviceBoundSessionSubscriber(true, $service, $container->get(StorefrontSubscriber::class), $container->get('router'), $container->get('request_stack'));

        $this->listeners = [
            KernelEvents::CONTROLLER => $subscriber->enforceBinding(...),
            StorefrontRouteScope::ID . '.scope.response' => $subscriber->offerRegistration(...),
            CustomerLogoutEvent::class => $subscriber->removeBinding(...),
        ];

        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->addListener(KernelEvents::CONTROLLER, $this->listeners[KernelEvents::CONTROLLER], KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE);
        $dispatcher->addListener(StorefrontRouteScope::ID . '.scope.response', $this->listeners[StorefrontRouteScope::ID . '.scope.response']);
        $dispatcher->addListener(CustomerLogoutEvent::class, $this->listeners[CustomerLogoutEvent::class]);

        // an initialized controller is the enabled one of a previous test, as nothing else routes to it
        if (!$container->initialized(DeviceBoundSessionController::class)) {
            $controller = new DeviceBoundSessionController(true, $service);
            $controller->setContainer($container);
            $container->set(DeviceBoundSessionController::class, $controller);
        }
    }

    private function login(): KernelBrowser
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('POST', $this->url('/account/login'), $this->tokenize('frontend.account.login', [
            'username' => $email,
            'password' => 'test12345',
        ]));
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->followRedirects(false);

        return $browser;
    }

    private function register(KernelBrowser $browser): string
    {
        $browser->request('GET', $this->url('/account'));
        $header = (string) $browser->getResponse()->headers->get('Secure-Session-Registration');
        if (!preg_match('/^\(ES256\);path="([^"]+)";challenge="([^"]+)"$/', $header, $matches)) {
            static::fail('Unexpected registration header: ' . $header);
        }

        $browser->request('POST', $this->url($matches[1]), server: [
            'HTTP_SECURE_SESSION_RESPONSE' => '"' . $this->deviceKey->signRegistration($matches[2]) . '"',
        ]);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $config = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($config);

        return $config['credentials'][0]['name'];
    }

    private function url(string $path): string
    {
        return EnvironmentHelper::getVariable('APP_URL') . $path;
    }

    private function sessionIdentifier(KernelBrowser $browser): string
    {
        $contextToken = $this->getSession()->get('sw-context-token');
        $id = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM device_bound_session WHERE context_token = ?', [$contextToken]);
        static::assertIsString($id);

        return $id;
    }

    private function sessionCookie(KernelBrowser $browser): Cookie
    {
        foreach ($browser->getCookieJar()->all() as $cookie) {
            if (str_starts_with($cookie->getName(), 'session-')) {
                return $cookie;
            }
        }

        static::fail('No session cookie was set');
    }

    private function assertLoggedIn(KernelBrowser $browser): void
    {
        $browser->request('GET', $this->url('/account'));

        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    private function assertLoggedOut(KernelBrowser $browser): void
    {
        $browser->request('GET', $this->url('/account'));

        static::assertSame(302, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('/account/login', (string) $browser->getResponse()->headers->get('Location'));
    }

    private function createCustomer(string $email): void
    {
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => 'test12345',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ]], Context::createDefaultContext());
    }
}
