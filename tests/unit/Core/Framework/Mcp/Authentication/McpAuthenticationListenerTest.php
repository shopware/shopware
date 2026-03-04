<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAuthenticationListener::class)]
class McpAuthenticationListenerTest extends TestCase
{
    public function testSkipsNonMcpRoutes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.some.other.route');

        $listener->authenticate($event);

        static::assertTrue($event->getRequest()->attributes->get('auth_required', true));
    }

    public function testSkipsWhenNoAccessKeyHeaders(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.mcp.endpoint');

        $listener->authenticate($event);

        static::assertTrue($event->getRequest()->attributes->get('auth_required', true));
    }

    public function testRejectsNonIntegrationKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $listener = new McpAuthenticationListener($connection);

        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWUAsomeuserkey1234567890',
            'sw-secret-access-key' => 'some-secret',
        ]);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Only integration access keys are supported');

        $listener->authenticate($event);
    }

    public function testRejectsInvalidAccessKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWIAvalidintegrationkey12',
            'sw-secret-access-key' => 'wrong-secret',
        ]);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Invalid integration access key');

        $listener->authenticate($event);
    }

    public function testRejectsInactiveApp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'abc123',
            'secret_access_key' => password_hash('correct-secret', \PASSWORD_BCRYPT),
            'app_active' => '0',
        ]);

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWIAvalidintegrationkey12',
            'sw-secret-access-key' => 'correct-secret',
        ]);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('app associated with this integration is inactive');

        $listener->authenticate($event);
    }

    public function testRejectsWrongSecret(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'abc123',
            'secret_access_key' => password_hash('correct-secret', \PASSWORD_BCRYPT),
            'app_active' => null,
        ]);

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => 'SWIAvalidintegrationkey12',
            'sw-secret-access-key' => 'wrong-secret',
        ]);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Invalid secret access key');

        $listener->authenticate($event);
    }

    public function testAuthenticatesSuccessfully(): void
    {
        $accessKey = 'SWIAvalidintegrationkey12';
        $secret = 'my-secret-key';

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'abc123',
            'secret_access_key' => password_hash($secret, \PASSWORD_BCRYPT),
            'app_active' => null,
        ]);
        $connection->expects($this->once())->method('update')
            ->with('integration', static::anything(), ['id' => 'abc123']);

        $listener = new McpAuthenticationListener($connection);
        $event = $this->createControllerEvent('api.mcp.endpoint', [
            'sw-access-key' => $accessKey,
            'sw-secret-access-key' => $secret,
        ]);

        $listener->authenticate($event);

        $request = $event->getRequest();
        static::assertSame('mcp-' . $accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID));
        static::assertSame($accessKey, $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID));
        static::assertFalse($request->attributes->get('auth_required'));
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
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
