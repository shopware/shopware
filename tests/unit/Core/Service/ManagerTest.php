<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ScheduledTask\InstallServicesTask;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(LifecycleManager::class)]
class ManagerTest extends TestCase
{
    private Privileges&MockObject $privileges;

    private SystemConfigService&MockObject $systemConfigService;

    private readonly AppLifecycle&MockObject $appLifecycle;

    private readonly MessageBusInterface&MockObject $messageBus;

    private Context $context;

    protected function setUp(): void
    {
        $this->privileges = $this->createMock(Privileges::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->appLifecycle = $this->createMock(AppLifecycle::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->context = Context::createDefaultContext();
    }

    public function testEnable(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->systemConfigService->expects($this->once())
            ->method('delete')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED);

        $message = new InstallServicesTask();

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf($message::class))
            ->willReturn(new Envelope($message));

        $manager = new LifecycleManager($this->privileges, $this->systemConfigService, $this->createAppRepository($services), $this->appLifecycle, $this->messageBus);

        $manager->enable();
    }

    public function testDisable(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $this->appLifecycle->expects($this->exactly($services->count()))
            ->method('delete');

        $manager = new LifecycleManager($this->privileges, $this->systemConfigService, $this->createAppRepository($services), $this->appLifecycle, $this->messageBus);

        $manager->disable($this->context);
    }

    public function testDisableWithNoServices(): void
    {
        $services = new AppCollection([]);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $this->appLifecycle->expects($this->never())
            ->method('delete');

        $manager = new LifecycleManager($this->privileges, $this->systemConfigService, $this->createAppRepository($services), $this->appLifecycle, $this->messageBus);

        $manager->disable($this->context);
    }

    public function testGrantPermissions(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->privileges
            ->expects($this->once())
            ->method('acceptAllForApps')
            ->with($services->getIds(), $this->context);

        $manager = new LifecycleManager($this->privileges, $this->systemConfigService, $this->createAppRepository($services), $this->appLifecycle, $this->messageBus);

        $manager->start($this->context);
    }

    public function testRevokePermissions(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->privileges
            ->expects($this->once())
            ->method('revokeAllForApps')
            ->with($services->getIds(), $this->context);

        $manager = new LifecycleManager($this->privileges, $this->systemConfigService, $this->createAppRepository($services), $this->appLifecycle, $this->messageBus);

        $manager->stop($this->context);
    }

    /**
     * @param array<string, bool> $systemConfig
     */
    #[DataProvider('servicesDisabledProvider')]
    public function testIsDisabled(array $systemConfig, bool $isDisabled): void
    {
        $managerWithDisabledServices = new LifecycleManager(
            $this->createMock(Privileges::class),
            new StaticSystemConfigService($systemConfig),
            $this->createAppRepository(),
            $this->createMock(AppLifecycle::class),
            $this->createMock(MessageBusInterface::class),
        );

        static::assertSame($isDisabled, $managerWithDisabledServices->enabled());
    }

    public static function servicesDisabledProvider(): \Generator
    {
        yield 'no system config present' => [[], false];

        yield 'system config set to true' => [['core.services.disabled' => true], true];

        yield 'system config set to false' => [['core.services.disabled' => false], false];
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function createAppRepository(AppCollection $apps = new AppCollection()): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            $apps,
        ]);

        return $appRepository;
    }
}
