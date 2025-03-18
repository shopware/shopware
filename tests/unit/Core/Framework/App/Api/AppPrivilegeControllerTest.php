<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Api\AppPrivilegeController;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AppPrivilegeController::class)]
class AppPrivilegeControllerTest extends TestCase
{
    private AppPrivilegeController $controller;

    private Connection&MockObject $connection;

    private Privileges&MockObject $privileges;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->privileges = $this->createMock(Privileges::class);
        $this->controller = new AppPrivilegeController($this->connection, $this->privileges);
    }

    public function testGetRequestedPrivilegesWithWrongSource(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();
        $this->controller->getRequestedPrivileges($context);
    }

    public function testGetRequestedPrivilegesWhenNotLoggedIn(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->controller->getRequestedPrivileges($context);
    }

    public function testGetRequestedPrivileges(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects($this->once())
            ->method('getRequestedPrivilegesForAllApps')
            ->with()
            ->willReturn([
                'App1' => ['customer:read', 'customer:update'],
                'App2' => ['product:read', 'product:update'],
            ]);

        $response = $this->controller->getRequestedPrivileges($context);

        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(
            [
                'requestedPrivileges' => [
                    'App1' => [
                        'customer' => [
                            [
                                'extensions' => [],
                                'entity' => 'customer',
                                'operation' => 'read',
                            ],
                            [
                                'extensions' => [],
                                'entity' => 'customer',
                                'operation' => 'update',
                            ],
                        ],
                    ],
                    'App2' => [
                        'product' => [
                            [
                                'extensions' => [],
                                'entity' => 'product',
                                'operation' => 'read',
                            ],
                            [
                                'extensions' => [],
                                'entity' => 'product',
                                'operation' => 'update',
                            ],
                        ],
                    ],
                ], ],
            $content
        );
    }

    public function testAcceptPrivilegesWithWrongSource(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();

        $request = new Request();
        $this->controller->acceptPrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWhenNotLoggedIn(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));

        $request = new Request();
        $this->controller->acceptPrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWithEmptyRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects($this->never())->method('acceptOnly');

        $request = new Request(content: (string) json_encode([]));

        static::expectException(AppException::class);
        static::expectExceptionMessage('Expected a list of privileges in the format "category:read"');

        $response = $this->controller->acceptPrivileges($request, $context, 'app-id-1');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPrivilegesWithMalformedRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects($this->never())->method('acceptOnly');

        $request = new Request(content: (string) json_encode(['id1', 'not-id' => []]));

        static::expectException(AppException::class);
        static::expectExceptionMessage('Expected a list of privileges in the format "category:read"');

        $response = $this->controller->acceptPrivileges($request, $context, 'app-id-1');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPrivilegesWithNonExistentAppName(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn(false);

        $this->privileges->expects($this->never())->method('acceptOnly');

        static::expectException(AppException::class);
        static::expectExceptionMessage('Could not find app with name "appName"');

        $request = new Request(content: (string) json_encode(['customer:read', 'customer:update']));
        $response = $this->controller->acceptPrivileges($request, $context, 'appName');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPrivileges(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn('app-id-1');

        $this->privileges->expects($this->once())
            ->method('acceptOnly')
            ->with('app-id-1', ['customer:read', 'customer:update'], $context);

        $request = new Request(content: (string) json_encode(['customer:read', 'customer:update']));
        $response = $this->controller->acceptPrivileges($request, $context, 'appName');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testGetAcceptedPrivilegesWithWrongSource(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();

        $this->controller->getAcceptedPrivileges($context);
    }

    public function testGetAcceptedPrivilegesWithMissingIntegration(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('An integration is required to perform this action');

        $source = new AdminApiSource('AABB', null);
        $context = Context::createDefaultContext($source);

        $this->controller->getAcceptedPrivileges($context);
    }

    public function testGetAcceptedPrivileges(): void
    {
        $source = new AdminApiSource('AABB', 'CCDD');
        $source->setPermissions(['customer:read', 'customer:update']);
        $context = Context::createDefaultContext($source);

        $response = $this->controller->getAcceptedPrivileges($context);

        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(
            [
                'acceptedPrivileges' => [
                    'customer' => [
                        [
                            'extensions' => [],
                            'entity' => 'customer',
                            'operation' => 'read',
                        ],
                        [
                            'extensions' => [],
                            'entity' => 'customer',
                            'operation' => 'update',
                        ],
                    ],
                ],
            ],
            $content
        );
    }
}
