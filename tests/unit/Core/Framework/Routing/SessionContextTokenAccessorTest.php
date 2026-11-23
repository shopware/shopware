<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SessionContextTokenAccessor::class)]
class SessionContextTokenAccessorTest extends TestCase
{
    private const SALES_CHANNEL = 'a-sales-channel';

    private const BINDING_ENABLED = ['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true];

    private const SUFFIXED_KEY = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . self::SALES_CHANNEL;

    public function testStartIgnoresRequestsThatAreNotStorefrontRequests(): void
    {
        $request = new Request();
        $request->setSession($this->session('any'));

        $this->accessor()->start($request);

        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartMintsATokenAndStampsTheSessionId(): void
    {
        $request = $this->storefrontRequest();
        $session = $this->session('a-session');
        $request->setSession($session);

        $this->accessor()->start($request);

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertSame($token, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testStartKeepsATokenTheSessionAlreadyHolds(): void
    {
        $request = $this->storefrontRequest();
        $session = $this->session('a-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'already-there');
        $request->setSession($session);

        $this->accessor()->start($request);

        static::assertSame('already-there', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartAlsoStampsTheCurrentSubRequest(): void
    {
        $mainRequest = $this->storefrontRequest();
        $mainRequest->setSession($this->session('a-session'));
        $subRequest = new Request();

        $this->accessor()->start($mainRequest, $subRequest);

        static::assertSame(
            $mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function testUnderBindingTheTokenIsKeptPerSalesChannel(): void
    {
        $request = $this->storefrontRequest();
        $session = $this->session('a-session');
        $session->set(self::SUFFIXED_KEY, 'channel-token');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'another-channels-token');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->start($request);

        static::assertSame('channel-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('channel-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAStorefrontRequestWithoutASalesChannelMintsAPerRequestToken(): void
    {
        $request = new Request(attributes: [SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $session = $this->session('a-session');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->start($request);

        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($session->has(self::SUFFIXED_KEY));
    }

    public function testReadReturnsNullWhenTheSessionIsNotDeclaredAsSource(): void
    {
        $request = $this->storeApiRequest();
        $request->headers->remove(PlatformRequest::HEADER_CONTEXT_SOURCE);

        static::assertNull($this->accessor()->read($request, self::SALES_CHANNEL));
    }

    public function testReadRejectsAnExplicitTokenAlongsideTheSessionSource(): void
    {
        $request = $this->storeApiRequest();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'an-explicit-token');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a sw-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->accessor()->read($request, self::SALES_CHANNEL);
    }

    public function testReadFailsWithoutASessionCookie(): void
    {
        $request = $this->storeApiRequest();
        $request->cookies->remove('session-');

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no storefront session cookie')
        );

        $this->accessor()->read($request, self::SALES_CHANNEL);
    }

    public function testReadFailsWhenTheCookieDoesNotResumeTheSession(): void
    {
        $request = $this->storeApiRequest();
        $request->cookies->set('session-', 'a-different-session');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->accessor()->read($request, self::SALES_CHANNEL);
    }

    public function testReadFailsWhenTheSessionHoldsNoToken(): void
    {
        $request = $this->storeApiRequest();

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a storefront session holding a context token for this sales channel'
        ));

        $this->accessor()->read($request, self::SALES_CHANNEL);
    }

    public function testReadReturnsTheSessionToken(): void
    {
        $request = $this->storeApiRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);

        static::assertSame('the-sessions-token', $this->accessor()->read($request, self::SALES_CHANNEL));
    }

    public function testReadUnderBindingPrefersTheSalesChannelKey(): void
    {
        $request = $this->storeApiRequest([
            PlatformRequest::HEADER_CONTEXT_TOKEN => 'another-channels-token',
            self::SUFFIXED_KEY => 'channel-token',
        ]);

        static::assertSame('channel-token', $this->accessor(self::BINDING_ENABLED)->read($request, self::SALES_CHANNEL));
    }

    public function testRotateReturnsFalseWithoutAResumableSession(): void
    {
        $request = new Request();

        static::assertFalse($this->accessor()->rotate($request, self::SALES_CHANNEL, 'rotated'));
    }

    public function testRotateMigratesTheStorefrontSession(): void
    {
        $request = $this->storefrontRequest();
        $session = $this->session('before');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'before-rotation');
        $request->setSession($session);

        static::assertTrue($this->accessor()->rotate($request, self::SALES_CHANNEL, 'rotated'));

        static::assertSame('rotated', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('rotated', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('before', $session->getId());
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testRotateMarksAStoreApiRequestAsSessionSourced(): void
    {
        $request = $this->storeApiRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'before-rotation']);

        static::assertTrue($this->accessor()->rotate($request, self::SALES_CHANNEL, 'rotated'));

        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testRotateUnderBindingWritesBothKeys(): void
    {
        $request = $this->storefrontRequest();
        $session = $this->session('before');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->rotate($request, self::SALES_CHANNEL, 'rotated');

        static::assertSame('rotated', $session->get(self::SUFFIXED_KEY));
        static::assertSame('rotated', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRotateCanDestroyTheOldSessionRecord(): void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('sw-session-', true);
        $storage = new MockFileSessionStorage($directory);
        $storage->setId('before');
        $session = new Session($storage);
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'before-rotation');
        $session->save();

        $request = $this->storefrontRequest();
        $request->setSession($session);

        try {
            $this->accessor()->rotate($request, self::SALES_CHANNEL, 'rotated', true);

            static::assertNotSame('before', $session->getId());
            static::assertSame([], glob($directory . '/before.mocksess') ?: []);
        } finally {
            if ($session->isStarted()) {
                $session->save();
            }
            array_map('unlink', glob($directory . '/*') ?: []);
            @rmdir($directory);
        }
    }

    /**
     * @param array<string, bool> $config
     */
    private function accessor(array $config = []): SessionContextTokenAccessor
    {
        return new SessionContextTokenAccessor(['name' => 'session-'], new StaticSystemConfigService($config));
    }

    private function session(string $id): Session
    {
        $storage = new MockArraySessionStorage();
        $storage->setId($id);

        return new Session($storage);
    }

    private function storefrontRequest(): Request
    {
        return new Request(attributes: [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => self::SALES_CHANNEL,
        ]);
    }

    /**
     * @param array<string, string> $sessionData
     */
    private function storeApiRequest(array $sessionData = []): Request
    {
        $session = $this->session('a-resumable-session');
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(cookies: ['session-' => 'a-resumable-session']);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->setSession($session);

        return $request;
    }
}
