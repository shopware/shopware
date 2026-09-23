<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AllServiceInstaller::class)]
class AllServiceInstallerTest extends TestCase
{
    private ServiceRegistryClient&Stub $registryClient;

    private ServiceLifecycle&MockObject $serviceLifecycle;

    private CollectingMessageBus $messageBus;

    private CollectingEventDispatcher $eventDispatcher;

    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->registryClient = static::createStub(ServiceRegistryClient::class);
        $this->serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $this->messageBus = new CollectingMessageBus();
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->logs = new TestHandler();
    }

    public function testDiscoveredServicesAreHandedToServiceLifecycle(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([$this->entry(name: 'Service1'), $this->entry(name: 'Service2')]);

        $this->serviceLifecycle->expects($this->exactly(2))->method('install')->willReturn(true);
        static::assertSame(['Service1', 'Service2'], $installer->reconcile(Context::createDefaultContext()));
        static::assertEquals([new NewServicesInstalledEvent()], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->messageBus->getMessages());
        static::assertSame([], $this->logs->getRecords());
    }

    public function testReconcileInstallsMissingAndUpdatesListedServicesOnly(): void
    {
        $installer = $this->installer($this->buildAppRepository([
            AppFixture::createAppEntity(name: 'Service1'),
            AppFixture::createAppEntity(name: 'OrphanedService'),
        ]));

        $this->registryClient->method('getAll')->willReturn([$this->entry(name: 'Service1'), $this->entry(name: 'Service2')]);

        $this->serviceLifecycle->expects($this->once())
            ->method('install')
            ->with(static::callback(static fn (ServiceEntry $entry): bool => $entry->name === 'Service2'))
            ->willReturn(true);
        $this->serviceLifecycle->expects($this->once())->method('update')->with('Service1');
        $this->serviceLifecycle->expects($this->never())->method('uninstall');

        static::assertSame(['Service2'], $installer->reconcile(Context::createDefaultContext()));
        static::assertEquals([new NewServicesInstalledEvent()], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->messageBus->getMessages());
        static::assertSame([], $this->logs->getRecords());
    }

    public function testReconcileContinuesWhenOneServiceFailsToInstall(): void
    {
        $installer = $this->installer($this->buildAppRepository([
            AppFixture::createAppEntity(name: 'FineUpdate'),
        ]));
        $this->registryClient->method('getAll')->willReturn([
            $this->entry(name: 'BrokenInstall'),
            $this->entry(name: 'FineInstall'),
            $this->entry(name: 'FineUpdate'),
        ]);
        $exception = AppXmlParsingException::cannotParseContent('Invalid manifest');
        $this->serviceLifecycle->expects($this->exactly(2))->method('install')->willReturnCallback(
            static fn (ServiceEntry $entry): bool => match ($entry->name) {
                'BrokenInstall' => throw $exception,
                default => true,
            }
        );
        $this->serviceLifecycle->expects($this->once())->method('update')->with('FineUpdate');

        static::assertSame(['FineInstall'], $installer->reconcile(Context::createDefaultContext()));
        static::assertEquals([new NewServicesInstalledEvent()], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->messageBus->getMessages());
        $records = $this->logs->getRecords();
        static::assertCount(1, $records);
        static::assertSame(Level::Warning, $records[0]->level);
        static::assertSame('Cannot install service', $records[0]->message);
        static::assertSame(['service' => 'BrokenInstall', 'exception' => $exception], $records[0]->context);
    }

    public function testReconcileContinuesWhenOneServiceFailsToUpdate(): void
    {
        $installer = $this->installer($this->buildAppRepository([
            AppFixture::createAppEntity(name: 'BrokenUpdate'),
            AppFixture::createAppEntity(name: 'FineUpdate'),
        ]));

        $this->registryClient->method('getAll')->willReturn([
            $this->entry(name: 'BrokenUpdate'),
            $this->entry(name: 'FineUpdate'),
        ]);

        $exception = AppXmlParsingException::cannotParseContent('Invalid manifest');

        $updated = [];
        $this->serviceLifecycle->expects($this->exactly(2))->method('update')
            ->willReturnCallback(static function (string $name) use ($exception, &$updated): void {
                if ($name === 'BrokenUpdate') {
                    throw $exception;
                }

                $updated[] = $name;
            });

        static::assertSame([], $installer->reconcile(Context::createDefaultContext()));
        static::assertSame(['FineUpdate'], $updated);
        static::assertSame([], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->messageBus->getMessages());
        $records = $this->logs->getRecords();
        static::assertCount(1, $records);
        static::assertSame(Level::Warning, $records[0]->level);
        static::assertSame('Cannot update service', $records[0]->message);
        static::assertSame(['service' => 'BrokenUpdate', 'exception' => $exception], $records[0]->context);
    }

    public function testReturnsOnlyTheServicesThatWereInstalled(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([
            $this->entry(name: 'Service1'),
            $this->entry(name: 'Service2'),
            $this->entry(name: 'Service3'),
        ]);

        $this->serviceLifecycle->expects($this->exactly(3))->method('install')->willReturnCallback(
            static fn (ServiceEntry $entry): bool => $entry->name !== 'Service2'
        );

        static::assertSame(['Service1', 'Service3'], $installer->reconcile(Context::createDefaultContext()));
        static::assertEquals([new NewServicesInstalledEvent()], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->messageBus->getMessages());
        static::assertSame([], $this->logs->getRecords());
    }

    public function testReconcileReturnsEmptyArrayWhenRegistryHasNoServices(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->registryClient->method('getAll')->willReturn([]);

        $this->serviceLifecycle->expects($this->never())->method('install');
        static::assertSame([], $installer->reconcile(Context::createDefaultContext()));
        static::assertSame([], $this->messageBus->getMessages());
        static::assertSame([], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->logs->getRecords());
    }

    public function testScheduleInstallDispatchesMessage(): void
    {
        $installer = $this->installer($this->buildAppRepository());

        $this->serviceLifecycle->expects($this->never())->method('install');
        $installer->scheduleInstall();

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertInstanceOf(InstallServicesMessage::class, $messages[0]->getMessage());
        static::assertSame([], $this->eventDispatcher->getEvents());
        static::assertSame([], $this->logs->getRecords());
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
            new Logger('test', [$this->logs]),
        );
    }

    private function entry(string $name): ServiceEntry
    {
        return new ServiceEntry($name, $name, 'https://' . $name . '.example.com', '/app-endpoint');
    }

    /**
     * @param list<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $apps = []): StaticEntityRepository
    {
        return new StaticEntityRepository([new AppCollection($apps)]);
    }
}
