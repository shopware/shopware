<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppLifecycle::class)]
class AppLifecycleTest extends TestCase
{
    public function testInstallDelegatesToAppManager(): void
    {
        $manifest = ManifestFixture::empty();
        $parameters = new AppInstallParameters();
        $context = Context::createDefaultContext();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('install')
            ->with($manifest, $parameters, $context);

        $appLifecycle = new AppLifecycle($appManager, new AppStorage(AppFixture::createAppRepository()));

        $appLifecycle->install($manifest, $parameters, $context);
    }

    public function testActivateLoadsAppAndDelegatesToAppManager(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        $context = Context::createDefaultContext();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('activate')
            ->with($app, $context);

        $appLifecycle = new AppLifecycle($appManager, new AppStorage(AppFixture::createAppRepository($app)));

        $appLifecycle->activate('app-id', $context);
    }

    public function testDeactivateLoadsAppAndDelegatesToAppManager(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        $context = Context::createDefaultContext();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('deactivate')
            ->with($app, $context);

        $appLifecycle = new AppLifecycle($appManager, new AppStorage(AppFixture::createAppRepository($app)));

        $appLifecycle->deactivate('app-id', $context);
    }

    public function testUpdateLoadsAppAndDelegatesToAppManager(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        $manifest = ManifestFixture::empty();
        $parameters = new AppUpdateParameters();
        $context = Context::createDefaultContext();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('update')
            ->with($manifest, $parameters, $app, $context);

        $appLifecycle = new AppLifecycle($appManager, new AppStorage(AppFixture::createAppRepository($app)));

        $appLifecycle->update($manifest, $parameters, ['id' => 'app-id'], $context);
    }

    public function testUninstallLoadsAppAndDelegatesToAppManager(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        $context = Context::createDefaultContext();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('uninstall')
            ->with($app, $context, true);

        $appLifecycle = new AppLifecycle($appManager, new AppStorage(AppFixture::createAppRepository($app)));

        $appLifecycle->uninstall('test', ['id' => 'app-id'], $context, true);
    }

    public function testUpdateThrowsWhenAppDoesNotExist(): void
    {
        $appLifecycle = new AppLifecycle(static::createStub(AppManager::class), new AppStorage(AppFixture::createAppRepository()));

        static::expectException(AppException::class);

        $appLifecycle->update(ManifestFixture::empty(), new AppUpdateParameters(), ['id' => 'missing'], Context::createDefaultContext());
    }

    public function testActivateThrowsWhenAppDoesNotExist(): void
    {
        $appLifecycle = new AppLifecycle(static::createStub(AppManager::class), new AppStorage(AppFixture::createAppRepository()));

        static::expectException(AppException::class);

        $appLifecycle->activate('missing', Context::createDefaultContext());
    }

    public function testGetDecoratedThrows(): void
    {
        $appLifecycle = new AppLifecycle(static::createStub(AppManager::class), new AppStorage(AppFixture::createAppRepository()));

        static::expectException(DecorationPatternException::class);

        $appLifecycle->getDecorated();
    }
}
