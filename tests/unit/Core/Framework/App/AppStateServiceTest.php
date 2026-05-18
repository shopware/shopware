<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Lifecycle\Persister\PersisterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AppStateService::class)]
class AppStateServiceTest extends TestCase
{
    public function testActivateThrowsIfAppDoesNotExist(): void
    {
        $this->expectException(AppNotFoundException::class);

        $this->buildService($this->buildAppRepository(), [])
            ->activateApp('missing-app', Context::createDefaultContext());
    }

    public function testDeactivateThrowsIfAppDoesNotExist(): void
    {
        $this->expectException(AppNotFoundException::class);

        $this->buildService($this->buildAppRepository(), [])
            ->deactivateApp('missing-app', Context::createDefaultContext());
    }

    public function testActivateUpdatesAppAndPersisters(): void
    {
        $context = Context::createDefaultContext();
        $app = $this->buildApp(active: false);
        $appRepository = $this->buildAppRepository($app);

        $persister = $this->createMock(PersisterInterface::class);
        $persister->expects($this->once())
            ->method('activate')
            ->with($app, $context);

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->expects($this->once())->method('reset');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $scriptExecutor = $this->createMock(ScriptExecutor::class);
        $scriptExecutor->expects($this->once())->method('execute');

        $appStateService = new AppStateService(
            $appRepository,
            $eventDispatcher,
            $activeAppsLoader,
            $scriptExecutor,
            [$persister],
        );

        $appStateService->activateApp($app->getId(), $context);

        static::assertTrue($app->isActive());
        static::assertSame([
            ['id' => $app->getId(), 'active' => true],
        ], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesAppAndPersisters(): void
    {
        $context = Context::createDefaultContext();
        $app = $this->buildApp(active: true);
        $appRepository = $this->buildAppRepository($app);

        $persister = $this->createMock(PersisterInterface::class);
        $persister->expects($this->once())
            ->method('deactivate')
            ->with($app, $context);

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->expects($this->once())->method('reset');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $scriptExecutor = $this->createMock(ScriptExecutor::class);
        $scriptExecutor->expects($this->once())->method('execute');

        $appStateService = new AppStateService(
            $appRepository,
            $eventDispatcher,
            $activeAppsLoader,
            $scriptExecutor,
            [$persister],
        );

        $appStateService->deactivateApp($app->getId(), $context);

        static::assertFalse($app->isActive());
        static::assertSame([
            ['id' => $app->getId(), 'active' => false],
        ], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateThrowsIfDisableIsNotAllowed(): void
    {
        $app = $this->buildApp(active: true, allowDisable: false);

        $this->expectException(AppException::class);

        $this->buildService($this->buildAppRepository($app), [])
            ->deactivateApp($app->getId(), Context::createDefaultContext());
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     * @param list<PersisterInterface> $persisters
     */
    private function buildService(StaticEntityRepository $appRepository, array $persisters): AppStateService
    {
        return new AppStateService(
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ActiveAppsLoader::class),
            $this->createMock(ScriptExecutor::class),
            $persisters,
        );
    }

    private function buildApp(bool $active, bool $allowDisable = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId('test-app');
        $app->setName('Test app');
        $app->setActive($active);
        $app->setAllowDisable($allowDisable);

        return $app;
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(AppEntity ...$apps): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([new AppCollection($apps)]);

        return $repository;
    }
}
