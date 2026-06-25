<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAuthenticationListener::class)]
class McpAuthenticationListenerTest extends TestCase
{
    public function testSubscribedEventsContainsControllerEvent(): void
    {
        $events = McpAuthenticationListener::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::CONTROLLER, $events);

        $controllerListeners = $events[KernelEvents::CONTROLLER];
        static::assertIsArray($controllerListeners);
        static::assertIsArray($controllerListeners[0]);
        static::assertSame('authenticate', $controllerListeners[0][0]);
        static::assertArrayHasKey(1, $controllerListeners[0]);
        static::assertSame(KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE, $controllerListeners[0][1]);
    }

    public function testSkipsNonMcpRoutes(): void
    {
        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->expects($this->never())->method('validateClient');

        $listener = new McpAuthenticationListener($clientRepository, $this->createMock(RateLimiter::class));
        $event = $this->createControllerEvent('api.some.other.route');

        $listener->authenticate($event);

        static::assertFalse($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED));
    }

    public function testFallsThroughWhenNoAccessKeyHeaders(): void
    {
        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->expects($this->never())->method('validateClient');

        $listener = new McpAuthenticationListener($clientRepository, $this->createMock(RateLimiter::class));
        $event = $this->createControllerEvent('api.mcp.endpoint');

        $listener->authenticate($event);

        static::assertFalse($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED));
    }

    public function testFallsThroughToBearerJwtWhenNoAccessKeyHeaders(): void
    {
        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->expects($this->never())->method('validateClient');

        $listener = new McpAuthenticationListener($clientRepository, $this->createMock(RateLimiter::class));
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'Authorization' => 'Bearer some.jwt.token',
        ]);

        $listener->authenticate($event);

        static::assertFalse($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED));
    }

    public function testRejectsUnsupportedKeyType(): void
    {
        $listener = new McpAuthenticationListener(
            static::createStub(ClientRepository::class),
            static::createStub(RateLimiter::class),
        );

        // SWSC = sales-channel key — a known prefix but not supported for MCP
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWSCsomesaleschannel1234',
            'sw-secret-access-key' => 'some-secret',
        ]);

        static::expectExceptionObject(McpException::unsupportedKeyType());

        $listener->authenticate($event);
    }

    public function testRejectsInvalidCredentialsAndDoesNotResetRateLimiter(): void
    {
        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->method('validateClient')
            ->with('SWIAvalidintegrationkey12', 'wrong-secret', 'client_credentials')
            ->willReturn(false);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted');
        $rateLimiter->expects($this->never())->method('reset');

        $listener = new McpAuthenticationListener($clientRepository, $rateLimiter);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWIAvalidintegrationkey12',
            'sw-secret-access-key' => 'wrong-secret',
        ]);

        static::expectExceptionObject(McpException::invalidCredentials());

        $listener->authenticate($event);
    }

    public function testRateLimitExceededPropagatesException(): void
    {
        $accessKey = 'SWIAvalidintegrationkey12';
        $expected = new RateLimitExceededException(time() + 60);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::OAUTH, $accessKey)
            ->willThrowException($expected);

        $listener = new McpAuthenticationListener(
            static::createStub(ClientRepository::class),
            $rateLimiter,
        );

        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => $accessKey,
            'sw-secret-access-key' => 'some-secret',
        ]);

        $this->expectExceptionObject($expected);

        $listener->authenticate($event);
    }

    public function testAuthenticatesSuccessfullyAndResetsRateLimiter(): void
    {
        $accessKey = 'SWIAvalidintegrationkey12';
        $secret = 'my-secret-key';

        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->method('validateClient')
            ->with($accessKey, $secret, 'client_credentials')
            ->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with(RateLimiter::OAUTH, $accessKey);
        $rateLimiter->expects($this->once())->method('reset')
            ->with(RateLimiter::OAUTH, $accessKey);

        $listener = new McpAuthenticationListener($clientRepository, $rateLimiter);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => $accessKey,
            'sw-secret-access-key' => $secret,
        ]);

        $listener->authenticate($event);

        $request = $event->getRequest();
        static::assertSame('mcp-' . $accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID));
        static::assertSame($accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID));
        static::assertTrue($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED));
    }

    public function testAuthenticatesSuccessfullyWithUserAccessKey(): void
    {
        $accessKey = 'SWUAvaliduseraccesskey123';
        $secret = 'my-secret-key';

        $clientRepository = $this->createMock(ClientRepository::class);
        $clientRepository->method('validateClient')
            ->with($accessKey, $secret, 'client_credentials')
            ->willReturn(true);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with(RateLimiter::OAUTH, $accessKey);
        $rateLimiter->expects($this->once())->method('reset')
            ->with(RateLimiter::OAUTH, $accessKey);

        $listener = new McpAuthenticationListener($clientRepository, $rateLimiter);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => $accessKey,
            'sw-secret-access-key' => $secret,
        ]);

        $listener->authenticate($event);

        $request = $event->getRequest();
        static::assertSame('mcp-' . $accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID));
        static::assertSame($accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID));
        static::assertTrue($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED));
    }

    /**
     * @param array<string, string> $headers
     */
    private function createControllerEvent(string $routeName, array $headers = []): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $routeName);
        $request->attributes->set('auth_required', true);

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
