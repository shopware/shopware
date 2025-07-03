<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(AllServiceInstaller::class)]
class AllServiceInstallerTest extends TestCase
{
    public function testAllServicesAreInstalledIfNoneExist(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
            $messageBus,
            $eventDispatcher
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('Service1', 'https://service1.example.com', 'Service 1', ''),
                new ServiceEntry('Service2', 'https://service2.example.com', 'Service 2', ''),
            ]);

        $matcher = $this->exactly(2);
        $serviceLifeCycle->expects($matcher)
            ->method('install')
            ->willReturnCallback(function (ServiceEntry $serviceRegistryEntry) use ($matcher): bool {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('Service1', $serviceRegistryEntry->name),
                    2 => $this->assertSame('Service2', $serviceRegistryEntry->name),
                    default => throw new \UnhandledMatchError(),
                };

                return true;
            });

        $eventDispatcher->expects($this->once())->method('dispatch');

        $serviceInstaller->install(Context::createDefaultContext());
    }

    public function testOnlyNewServicesAreInstalled(): void
    {
        $app1 = new AppEntity();
        $app1->setUniqueIdentifier(Uuid::randomHex());
        $app1->setName('Service1');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository([$app1]),
            $messageBus,
            $eventDispatcher
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('Service1', 'Service 1', 'https://service1.example.com', '/app-endpoint'),
                new ServiceEntry('Service2', 'Service 2', 'https://service2.example.com', '/app-endpoint'),
            ]);

        $serviceLifeCycle->expects($this->exactly(1))
            ->method('install')
            ->willReturnCallback(function (ServiceEntry $serviceRegistryEntry): bool {
                $this->assertSame('Service2', $serviceRegistryEntry->name);

                return true;
            });

        $eventDispatcher->expects($this->once())->method('dispatch');

        $serviceInstaller->install(Context::createDefaultContext());
    }

    public function testNoServicesAreInstalledIfAllExist(): void
    {
        $app1 = new AppEntity();
        $app1->setUniqueIdentifier(Uuid::randomHex());
        $app1->setName('Service1');
        $app2 = new AppEntity();
        $app2->setUniqueIdentifier(Uuid::randomHex());
        $app2->setName('Service2');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository([$app1, $app2]),
            $messageBus,
            $eventDispatcher,
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('Service1', 'Service 1', 'https://service1.example.com', '/app-endpoint'),
                new ServiceEntry('Service2', 'Service 2', 'https://service2.example.com', '/app-endpoint'),
            ]);

        $serviceLifeCycle->expects($this->never())
            ->method('install');

        $eventDispatcher->expects($this->never())->method('dispatch');

        $serviceInstaller->install(Context::createDefaultContext());
    }

    public function testScheduleInstallDispatchesMessage(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
            $messageBus,
            $eventDispatcher
        );

        $envelope = new Envelope(new \stdClass());
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($message) {
                return $message instanceof InstallServicesMessage;
            }))
            ->willReturn($envelope);

        $serviceInstaller->scheduleInstall();
    }

    public function testInstallReturnsEmptyArrayWhenNoServicesAvailable(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
            $messageBus,
            $eventDispatcher,
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([]);

        $serviceLifeCycle->expects($this->never())
            ->method('install');

        $result = $serviceInstaller->install(Context::createDefaultContext());

        static::assertSame([], $result);
    }

    public function testInstallHandlesFailedServiceInstallation(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
            $messageBus,
            $eventDispatcher,
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('SuccessfulService', 'https://successful.example.com', 'Service 1', ''),
                new ServiceEntry('FailingService', 'https://failing.example.com', 'Service 2', ''),
            ]);

        $matcher = $this->exactly(2);
        $serviceLifeCycle->expects($matcher)
            ->method('install')
            ->willReturnCallback(function () use ($matcher): bool {
                return match ($matcher->numberOfInvocations()) {
                    1 => true,
                    2 => false,
                    default => throw new \UnhandledMatchError(),
                };
            });

        $result = $serviceInstaller->install(Context::createDefaultContext());

        static::assertSame(['SuccessfulService'], $result);
    }

    public function testInstallOnlyReturnsSuccessfullyInstalledServices(): void
    {
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceLifeCycle = $this->createMock(ServiceLifecycle::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $serviceInstaller = new AllServiceInstaller(
            $serviceRegistryClient,
            $serviceLifeCycle,
            $this->buildAppRepository(),
            $messageBus,
            $eventDispatcher,
        );

        $serviceRegistryClient->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('Service1', 'https://service1.example.com', 'Service 1', ''),
                new ServiceEntry('Service2', 'https://service2.example.com', 'Service 2', ''),
                new ServiceEntry('Service3', 'https://service3.example.com', 'Service 3', ''),
            ]);

        $matcher = $this->exactly(3);
        $serviceLifeCycle->expects($matcher)
            ->method('install')
            ->willReturnCallback(function () use ($matcher): bool {
                return match ($matcher->numberOfInvocations()) {
                    1 => true,
                    2 => false,
                    3 => true,
                    default => throw new \UnhandledMatchError(),
                };
            });

        $result = $serviceInstaller->install(Context::createDefaultContext());

        static::assertSame(['Service1', 'Service3'], $result);
    }

    /**
     * @param array<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $apps = []): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection($apps),
        ]);

        return $appRepository;
    }
}
