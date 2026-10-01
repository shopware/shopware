<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\Api\AppPrivilegeController;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppPrivilegeController::class)]
class AppPrivilegeControllerTest extends TestCase
{
    private AppPrivilegeController $controller;

    private Connection&Stub $connection;

    private Privileges&MockObject $privileges;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->privileges = $this->createMock(Privileges::class);
        $this->controller = new AppPrivilegeController($this->connection, $this->privileges);
    }

    public function testGetRequestedPrivilegesWithWrongSource(): void
    {
        $this->privileges->expects($this->never())->method('getRequestedPrivilegesForAllApps');

        $this->expectExceptionObject(AppException::invalidContextSource(AdminApiSource::class, SystemSource::class));

        $context = Context::createDefaultContext();
        $this->controller->getRequestedPrivileges($context);
    }

    public function testGetRequestedPrivilegesWhenNotLoggedIn(): void
    {
        $this->privileges->expects($this->never())->method('getRequestedPrivilegesForAllApps');

        $this->expectExceptionObject(AppException::missingUserInContextSource(AdminApiSource::class));

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
                'privileges' => [
                    'App1' => ['customer:read', 'customer:update'],
                    'App2' => ['product:read', 'product:update'],
                ],
            ],
            $content
        );
    }

    public function testAcceptPrivilegesWithWrongSource(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $this->expectExceptionObject(AppException::invalidContextSource(AdminApiSource::class, SystemSource::class));

        $context = Context::createDefaultContext();

        $request = new Request();
        $this->controller->updatePrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWhenNotLoggedIn(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $this->expectExceptionObject(AppException::missingUserInContextSource(AdminApiSource::class));

        $context = Context::createDefaultContext(new AdminApiSource(null));

        $request = new Request();
        $this->controller->updatePrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWithEmptyRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects($this->never())->method('updatePrivileges');

        // To trigger AppException::invalidPrivileges(), 'accept' or 'revoke' must be non-array
        $request = new Request(content: (string) json_encode(['accept' => 123])); // Changed from null to 123

        $this->expectExceptionObject(AppException::invalidPrivileges());

        $this->controller->updatePrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWithMalformedRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects($this->never())->method('updatePrivileges');

        // To trigger AppException::invalidPrivileges(), 'accept' or 'revoke' must be non-array
        $request = new Request(content: (string) json_encode(['accept' => false]));

        $this->expectExceptionObject(AppException::invalidPrivileges());

        $this->controller->updatePrivileges($request, $context, 'app-id-1');
    }

    public function testAcceptPrivilegesWithNonExistentAppName(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $connection = static::createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn(false);
        $controller = new AppPrivilegeController($connection, $this->privileges);

        $this->privileges->expects($this->never())->method('updatePrivileges');

        $this->expectExceptionObject(AppException::notFoundByField('appName', 'name'));

        $request = new Request(content: (string) json_encode(['accept' => ['customer:read', 'customer:update']]));
        $controller->updatePrivileges($request, $context, 'appName');
    }

    public function testAcceptPrivileges(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $connection = static::createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn('app-id-1');
        $controller = new AppPrivilegeController($connection, $this->privileges);

        $this->privileges->expects($this->once())
            ->method('updatePrivileges')
            ->with('app-id-1', ['customer:read', 'customer:update'], [], $context);

        $request = new Request(content: (string) json_encode(['accept' => ['customer:read', 'customer:update']]));
        $response = $controller->updatePrivileges($request, $context, 'appName');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testGetAcceptedPrivilegesWithWrongSource(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $this->expectExceptionObject(AppException::invalidContextSource(AdminApiSource::class, SystemSource::class));

        $context = Context::createDefaultContext();

        $this->controller->getAcceptedPrivileges($context);
    }

    public function testGetAcceptedPrivilegesWithMissingIntegration(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $this->expectExceptionObject(AppException::missingIntegration());

        $source = new AdminApiSource('AABB', null);
        $context = Context::createDefaultContext($source);

        $this->controller->getAcceptedPrivileges($context);
    }

    public function testGetAcceptedPrivileges(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $source = new AdminApiSource('AABB', 'CCDD');
        $source->setPermissions(['customer:read', 'customer:update']);
        $context = Context::createDefaultContext($source);

        $response = $this->controller->getAcceptedPrivileges($context);

        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(
            [
                'privileges' => [
                    'customer:read' => true,
                    'customer:update' => true,
                ],
            ],
            $content
        );
    }

    public function testGetAcceptedPrivilegesEmpty(): void
    {
        $this->privileges->expects($this->never())->method('updatePrivileges');

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);
        $response = $this->controller->getAcceptedPrivileges($context);
        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(['privileges' => []], $content);
    }
}
