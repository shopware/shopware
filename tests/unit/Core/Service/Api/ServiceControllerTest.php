<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Api\ServiceController;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ServiceController::class)]
class ServiceControllerTest extends TestCase
{
    private string $appId;

    private AppEntity $app;

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepo;

    private MessageBusInterface&MockObject $bus;

    private ServiceLifecycle&MockObject $serviceLifecycle;

    private LifecycleManager&MockObject $manager;

    protected function setUp(): void
    {
        $this->appId = Uuid::randomHex();
        $this->app = AppFixture::createAppEntity(name: 'MyCoolService', id: $this->appId);
        $this->app->setIntegrationId('CCDD');
        $this->app->setSourceConfig([
            'requirements' => ['service_consent'],
        ]);

        $this->appRepo = new StaticEntityRepository([new AppCollection([$this->app])]);

        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $this->manager = $this->createMock(LifecycleManager::class);
    }

    public function testExceptionIsThrownIfServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('integrationId', 'CCDD'));

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([new AppCollection()]);

        $this->bus->expects($this->never())->method('dispatch');

        $controller = new ServiceController(new ServiceStorage($appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $this->bus->expects($this->never())->method('dispatch');

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNoIntegrationId(): void
    {
        static::expectExceptionObject(ServiceException::updateRequiresIntegration());

        $this->bus->expects($this->never())->method('dispatch');

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testUpdateIsTriggered(): void
    {
        $this->bus->expects($this->once())->method('dispatch')->willReturnCallback(static function (UpdateServiceMessage $msg) {
            static::assertSame('MyCoolService', $msg->name);

            return new Envelope($msg, []);
        });

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testActivateThrownExceptionIfInvalidName(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'invalidService'));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $source->setPermissions(['api_service_toggle']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())
            ->method('activate')
            ->with('invalidService', $context)
            ->willThrowException(ServiceException::notFound('name', 'invalidService'));

        $controller->activate('invalidService', $context);
    }

    public function testActivateThrownExceptionIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testActivateThrownExceptionIfAdminUserCannotMaintainPlugins(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['system.plugin_maintain']));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testActivateThrownExceptionIfIntegrationCannotToggleServices(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['api_service_toggle']));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testActivate(): void
    {
        $this->app->setActive(false);
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'EEFF');
        $source->setPermissions(['api_service_toggle']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())->method('activate')->with('MyCoolService', $context);
        $controller->activate('MyCoolService', $context);
    }

    public function testAdminActivateUsesSameAction(): void
    {
        $this->app->setActive(false);
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB');
        $source->setPermissions(['system.plugin_maintain']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())->method('activate')->with('MyCoolService', $context);
        $controller->activate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfInvalidName(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'invalidService'));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $source->setPermissions(['api_service_toggle']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())
            ->method('deactivate')
            ->with('invalidService', $context)
            ->willThrowException(ServiceException::notFound('name', 'invalidService'));

        $controller->deactivate('invalidService', $context);
    }

    public function testDeactivateThrownExceptionIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $context = Context::createDefaultContext($source);

        $controller->deactivate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfAdminUserCannotMaintainPlugins(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['system.plugin_maintain']));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->deactivate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfIntegrationCannotToggleServices(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['api_service_toggle']));

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $controller->deactivate('MyCoolService', $context);
    }

    public function testDeactivate(): void
    {
        $this->app->setActive(true);
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'EEFF');
        $source->setPermissions(['api_service_toggle']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())->method('deactivate')->with('MyCoolService', $context);
        $controller->deactivate('MyCoolService', $context);
    }

    public function testAdminDeactivateUsesSameAction(): void
    {
        $this->app->setActive(true);
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB');
        $source->setPermissions(['system.plugin_maintain']);
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())->method('deactivate')->with('MyCoolService', $context);
        $controller->deactivate('MyCoolService', $context);
    }

    public function testUninstall(): void
    {
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $this->serviceLifecycle->expects($this->once())->method('uninstall')->with('MyCoolService', $context);
        $controller->uninstall('MyCoolService', $context);
    }

    public function testList(): void
    {
        $this->app->setActive(true);
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $response = $controller->list($context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true);
        static::assertIsArray($responseData);
        static::assertCount(1, $responseData);

        $service = $responseData[0];
        static::assertSame($this->appId, $service['id']);
        static::assertSame('MyCoolService', $service['name']);
        static::assertTrue($service['active']);
        static::assertSame('1.0.0', $service['version']);
        static::assertSame('active', $service['state']);
        static::assertSame(['service_consent'], $service['requirements']);
    }

    public function testDisableServices(): void
    {
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $this->manager->expects($this->once())->method('disable');
        $controller->disableServices($context);
    }

    public function testEnableServices(): void
    {
        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $this->manager->expects($this->once())->method('enable');
        $controller->enableServices();
    }

    public function testReturnsCategorizedPermissions(): void
    {
        $aclRole = new AclRoleEntity();
        $aclRole->setPrivileges([
            'user:read',
            'order:read',
            'api_service_toggle',
        ]);

        $this->app->setAclRole($aclRole);
        $this->app->setRequestedPrivileges([
            'product:read',
        ]);

        $controller = new ServiceController(new ServiceStorage($this->appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        $response = $controller->categorizedPermissions('MyCoolService', Context::createDefaultContext());
        $content = $response->getContent();

        static::assertIsString($content);
        $categorizedPermissions = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertEquals([
            'permissions' => [
                'admin_user' => [
                    [
                        'extensions' => [],
                        'entity' => 'user',
                        'operation' => 'read',
                    ],
                ],
                'order' => [
                    [
                        'extensions' => [],
                        'entity' => 'order',
                        'operation' => 'read',
                    ],
                ],
                'product' => [
                    [
                        'extensions' => [],
                        'entity' => 'product',
                        'operation' => 'read',
                    ],
                ],
                'additional_privileges' => [
                    [
                        'extensions' => [],
                        'entity' => 'additional_privileges',
                        'operation' => 'api_service_toggle',
                    ],
                ],
            ],
        ], $categorizedPermissions);
    }

    public function testCategorizedPermissionsThrowsIfServiceIsNotFound(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([new AppCollection()]);

        $controller = new ServiceController(new ServiceStorage($appRepo), $this->bus, $this->serviceLifecycle, $this->manager);

        static::expectExceptionObject(ServiceException::notFound('name', 'MyCoolService'));
        $controller->categorizedPermissions('MyCoolService', Context::createDefaultContext());
    }
}
