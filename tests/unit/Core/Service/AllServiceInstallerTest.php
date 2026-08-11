<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AllServiceInstaller::class)]
class AllServiceInstallerTest extends TestCase
{
    private ServiceRegistryClient&Stub $registryClient;

    private ServiceLifecycle&MockObject $serviceLifecycle;

    private MessageBusInterface&MockObject $messageBus;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->registryClient = static::createStub(ServiceRegistryClient::class);
        $this->serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testDiscoveredServicesAreHandedToServiceLifecycle(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([$this->entry('Service1'), $this->entry('Service2')]);

        $this->serviceLifecycle->expects($this->exactly(2))->method('install')->willReturn(true);
        $this->eventDispatcher->expects($this->once())->method('dispatch');

        // A fresh shop only installs; reconcile must not enqueue update messages when nothing is installed yet.
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        static::assertSame(['Service1', 'Service2'], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testOnlyUpdatesAreScheduledWhenAllServicesAreInstalled(): void
    {
        $installer = $this->installer($this->buildAppRepository([
            AppFixture::createAppEntity(name: 'Service1'),
            AppFixture::createAppEntity(name: 'Service2'),
        ]));

        $this->registryClient->method('getAll')->willReturn([$this->entry('Service1'), $this->entry('Service2')]);

        $this->serviceLifecycle->expects($this->never())->method('install');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(UpdateServiceMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger->expects($this->once())->method('debug');

        static::assertSame([], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testReturnsOnlyTheServicesThatWereInstalled(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([
            $this->entry('Service1'),
            $this->entry('Service2'),
            $this->entry('Service3'),
        ]);

        $this->serviceLifecycle->expects($this->exactly(3))->method('install')->willReturnCallback(
            static fn (ServiceEntry $entry): bool => $entry->name !== 'Service2'
        );

        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        static::assertSame(['Service1', 'Service3'], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testReconcileContinuesWhenAServiceThrowsDuringInstallation(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([$this->entry('BrokenService'), $this->entry('ValidService')]);

        $exception = AppXmlParsingException::cannotParseContent('Invalid manifest');
        $this->serviceLifecycle->expects($this->exactly(2))->method('install')->willReturnCallback(
            static fn (ServiceEntry $entry): bool => match ($entry->name) {
                'BrokenService' => throw $exception,
                default => true,
            }
        );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(\sprintf('Cannot install service "BrokenService" because of error: "%s"', $exception->getMessage()));

        // the throw from BrokenService must not prevent ValidService from being installed
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->messageBus->expects($this->never())->method('dispatch');

        static::assertSame(['ValidService'], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testReconcileReturnsEmptyArrayWhenRegistryHasNoServices(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([]);

        $this->serviceLifecycle->expects($this->never())->method('install');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        static::assertSame([], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testReconcileDoesNotDispatchUpdateMessageForOrphanedService(): void
    {
        $installer = $this->installer($this->buildAppRepository([
            AppFixture::createAppEntity(name: 'Service1'),
            AppFixture::createAppEntity(name: 'OrphanedService'),
        ]));

        $this->registryClient->method('getAll')->willReturn([$this->entry('Service1')]);

        $this->serviceLifecycle->expects($this->never())->method('install');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->once())->method('debug');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($message): bool => $message instanceof UpdateServiceMessage && $message->name === 'Service1'))
            ->willReturn(new Envelope(new \stdClass()));

        $installer->reconcile(Context::createDefaultContext());
    }

    public function testReconcileInstallsNewServicesWithoutEnqueuingUpdateForThem(): void
    {
        $installer = $this->installer($this->buildAppRepository([AppFixture::createAppEntity(name: 'Service1')]));

        $this->registryClient->method('getAll')->willReturn([$this->entry('Service1'), $this->entry('Service2')]);

        $this->serviceLifecycle->expects($this->once())
            ->method('install')
            ->willReturnCallback(function (ServiceEntry $entry): bool {
                static::assertSame('Service2', $entry->name);

                return true;
            });

        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->once())->method('debug');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($message): bool => $message instanceof UpdateServiceMessage && $message->name === 'Service1'))
            ->willReturn(new Envelope(new \stdClass()));

        static::assertSame(['Service2'], $installer->reconcile(Context::createDefaultContext()));
    }

    public function testScheduleInstallDispatchesMessage(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->serviceLifecycle->expects($this->never())->method('install');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($message): bool => $message instanceof InstallServicesMessage))
            ->willReturn(new Envelope(new \stdClass()));

        $installer->scheduleInstall();
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     */
    private function installer(StaticEntityRepository $appRepository): AllServiceInstaller
    {
        return new AllServiceInstaller(
            $this->registryClient,
            new ServiceStorage($appRepository),
            $this->serviceLifecycle,
            $this->messageBus,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    private function entry(string $name): ServiceEntry
    {
        return new ServiceEntry($name, $name, 'https://' . $name . '.example.com', '/app-endpoint');
    }

    /**
     * @param array<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $apps = []): StaticEntityRepository
    {
        $appRepository = new StaticEntityRepository([new AppCollection($apps)]);

        return $appRepository;
    }
}
