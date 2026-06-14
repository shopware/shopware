<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AppStateService::class)]
class AppStateServiceTest extends TestCase
{
    public function testDelegatesToAppManager(): void
    {
        $context = Context::createDefaultContext();
        $activateApp = AppFixture::createAppEntity(id: 'activate-app-id');
        $deactivateApp = AppFixture::createAppEntity(id: 'deactivate-app-id');
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection([$activateApp]),
            new AppCollection([$deactivateApp]),
        ]);

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('activate')
            ->with($activateApp, $context);

        $appManager->expects($this->once())
            ->method('deactivate')
            ->with($deactivateApp, $context, true);

        $appStateService = new AppStateService($appManager, $appRepository);
        $appStateService->activateApp('activate-app-id', $context);
        $appStateService->deactivateApp('deactivate-app-id', $context, true);
    }

    public function testThrowsIfAppDoesNotExist(): void
    {
        $appStateService = new AppStateService(
            $this->createMock(AppManager::class),
            AppFixture::createAppRepository(),
        );

        $this->expectExceptionObject(AppException::notFound('missing-app-id'));

        $appStateService->activateApp('missing-app-id', Context::createDefaultContext());
    }
}
