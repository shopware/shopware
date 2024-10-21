<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Api\ServiceController;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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

    private AppStateService&MockObject $appStateService;

    private AppLifecycle&MockObject $appLifecycle;

    protected function setUp(): void
    {
        $this->appId = Uuid::randomHex();
        $this->app = new AppEntity();
        $this->app->setId($this->appId);
        $this->app->setUniqueIdentifier($this->appId);
        $this->app->assign(['name' => 'MyCoolService', 'integrationId' => 'CCDD']);

        $this->appRepo = new StaticEntityRepository([[$this->app]]);

        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->appStateService = $this->createMock(AppStateService::class);
        $this->appLifecycle = $this->createMock(AppLifecycle::class);
    }

    public function testExceptionIsThrownIfServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('integrationId', 'CCDD'));

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[]]);

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNoIntegrationId(): void
    {
        static::expectExceptionObject(ServiceException::updateRequiresIntegration());

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testUpdateIsTriggered(): void
    {
        $this->bus->expects(static::once())->method('dispatch')->willReturnCallback(function (UpdateServiceMessage $msg) {
            static::assertSame('MyCoolService', $msg->name);

            return new Envelope($msg, []);
        });

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testActivateThrownExceptionIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testActivateThrownExceptionIfNoIntegrationId(): void
    {
        static::expectExceptionObject(ServiceException::updateRequiresIntegration());

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testActivateThrownExceptionIfInvalidName(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'invalidService'));

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[]]);
        $controller = new ServiceController($appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->activate('invalidService', $context);
    }

    public function testActivate(): void
    {
        $this->app->setActive(false);
        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $this->appStateService->expects(static::once())->method('activateApp')->with($this->appId, $context);
        $controller->activate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServiceException::updateRequiresAdminApiSource($source));

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $context = Context::createDefaultContext($source);

        $controller->activate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfNoIntegrationId(): void
    {
        static::expectExceptionObject(ServiceException::updateRequiresIntegration());

        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->deactivate('MyCoolService', $context);
    }

    public function testDeactivateThrownExceptionIfInvalidName(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'invalidService'));

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[]]);
        $controller = new ServiceController($appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->deactivate('invalidService', $context);
    }

    public function testDeactivate(): void
    {
        $this->app->setActive(true);
        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $this->appStateService->expects(static::once())->method('deactivateApp')->with($this->appId, $context);
        $controller->deactivate('MyCoolService', $context);
    }

    public function testUninstall(): void
    {
        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'EEFF');
        $context = Context::createDefaultContext($source);

        $this->appLifecycle->expects(static::once())->method('delete')->with($this->appId, ['id' => $this->appId], $context);
        $controller->uninstall('MyCoolService', $context);
    }

    public function testList(): void
    {
        $this->app->setActive(true);
        $controller = new ServiceController($this->appRepo, $this->bus, $this->appStateService, $this->appLifecycle);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $response = $controller->list($context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame([['id' => $this->appId, 'name' => 'MyCoolService', 'active' => true]], json_decode((string) $response->getContent(), true));
    }
}
