<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Command\UninstallAppCommandDecorator;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UninstallAppCommandDecorator::class)]
class UninstallAppCommandDecoratorTest extends TestCase
{
    private AbstractAppLifecycle&MockObject $appLifecycle;

    private AppStorage&MockObject $appStorage;

    private ServiceStorage&MockObject $serviceStorage;

    private ServiceLifecycle&MockObject $serviceLifecycle;

    private LifecycleManager&Stub $lifecycleManager;

    private UninstallAppCommandDecorator $command;

    protected function setUp(): void
    {
        $this->appLifecycle = static::createMock(AbstractAppLifecycle::class);
        $this->appStorage = static::createMock(AppStorage::class);
        $this->serviceStorage = static::createMock(ServiceStorage::class);
        $this->serviceLifecycle = static::createMock(ServiceLifecycle::class);
        $this->lifecycleManager = static::createStub(LifecycleManager::class);

        $this->command = new UninstallAppCommandDecorator(
            $this->appLifecycle,
            $this->appStorage,
            $this->serviceStorage,
            $this->serviceLifecycle,
            $this->lifecycleManager
        );
    }

    public function testRunsAppUninstallWhenServicesAreEnabled(): void
    {
        $this->lifecycleManager->method('enabled')->willReturn(true);
        $this->serviceStorage->expects($this->never())->method('findByName');
        $this->serviceLifecycle->expects($this->never())->method('uninstall');
        $this->expectAppUninstall('MyService', false);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'MyService']);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('App uninstalled successfully.', $tester->getDisplay());
    }

    public function testUninstallsServiceWhenServicesAreDisabled(): void
    {
        $this->lifecycleManager->method('enabled')->willReturn(false);
        $app = AppFixture::createAppEntity(name: 'MyService', id: 'service-id');
        $app->setSelfManaged(true);
        $service = Service::fromApp($app);

        $this->serviceStorage->expects($this->once())->method('findByName')->with('MyService', static::isInstanceOf(Context::class))->willReturn($service);
        $this->appStorage->expects($this->never())->method('findByName');
        $this->appLifecycle->expects($this->never())->method('uninstall');

        $this->serviceLifecycle->expects($this->once())->method('uninstall')->with('MyService', static::isInstanceOf(Context::class));

        $tester = new CommandTester($this->command);
        $status = $tester->execute([
            'name' => 'MyService',
        ]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('App uninstalled successfully.', $tester->getDisplay());
    }

    public function testRunsAppUninstallWhenServiceIsNotFound(): void
    {
        $this->lifecycleManager->method('enabled')->willReturn(false);
        $this->serviceStorage->expects($this->once())->method('findByName')->willReturn(null);
        $this->serviceLifecycle->expects($this->never())->method('uninstall');
        $this->expectAppUninstall('MyService', false);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['name' => 'MyService']);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('App uninstalled successfully.', $tester->getDisplay());
    }

    private function expectAppUninstall(string $name, bool $keepUserData): void
    {
        $app = AppFixture::createAppEntity(name: $name, id: 'app-id');

        $this->appStorage->expects($this->once())->method('findByName')->with($name, static::isInstanceOf(Context::class))->willReturn($app);
        $this->appLifecycle->expects($this->once())->method('uninstall')->with(
            $name,
            ['id' => 'app-id'],
            static::isInstanceOf(Context::class),
            $keepUserData
        );
    }
}
