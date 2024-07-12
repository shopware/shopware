<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\Exception\PluginBaseClassNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(PluginLifecycleService::class)]
class PluginLifecycleServiceTest extends TestCase
{
    private PluginLifecycleService $pluginLifecycleService;

    private MockObject&EntityRepository $pluginRepoMock;

    private MockObject&KernelPluginCollection $kernelPluginCollectionMock;

    private Container $container;

    private MockObject&MigrationCollectionLoader $migrationLoaderMock;

    private MockObject&RequirementsValidator $requirementsValidatorMock;

    private MockObject&CacheItemPoolInterface $cacheItemPoolInterfaceMock;

    private MockObject&Plugin $pluginMock;

    private CollectingEventDispatcher $eventDispatcher;

    private MockObject&PluginService $pluginServiceMock;

    private CommandExecutor&MockObject $commandExecutor;

    protected function setUp(): void
    {
        $this->pluginRepoMock = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->kernelPluginCollectionMock = $this->createMock(KernelPluginCollection::class);
        $this->container = new ContainerBuilder();
        $this->migrationLoaderMock = $this->createMock(MigrationCollectionLoader::class);
        $this->requirementsValidatorMock = $this->createMock(RequirementsValidator::class);
        $this->cacheItemPoolInterfaceMock = $this->createMock(CacheItemPoolInterface::class);
        $this->pluginServiceMock = $this->createMock(PluginService::class);
        $this->commandExecutor = $this->createMock(CommandExecutor::class);

        $this->container->setParameter('shopware.deployment.cluster_setup', false);

        $this->pluginMock = $this->createMock(Plugin::class);

        $this->pluginMock->method('getNamespace')->willReturn('MockPlugin');
        $this->pluginMock->method('getMigrationNamespace')->willReturn('migration');

        $this->pluginLifecycleService = new PluginLifecycleService(
            $this->pluginRepoMock,
            $this->eventDispatcher,
            $this->kernelPluginCollectionMock,
            $this->container,
            $this->migrationLoaderMock,
            $this->createMock(AssetService::class),
            $this->commandExecutor,
            $this->requirementsValidatorMock,
            $this->cacheItemPoolInterfaceMock,
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->createMock(SystemConfigService::class),
            $this->createMock(CustomEntityPersister::class),
            $this->createMock(CustomEntitySchemaUpdater::class),
            $this->createMock(CustomEntityLifecycleService::class),
            $this->pluginServiceMock,
            $this->createMock(VersionSanitizer::class),
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = PluginLifecycleService::getSubscribedEvents();

        static::assertCount(1, $subscribedEvents);
        static::assertArrayHasKey(KernelEvents::RESPONSE, $subscribedEvents);
        static::assertEquals(['onResponse', \PHP_INT_MIN], $subscribedEvents[KernelEvents::RESPONSE]);
    }

    // +++++ InstallPlugin method ++++

    public function testInstallPlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('postInstall');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreInstallEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostInstallEvent::class, $returnedEvents[1]);
        static::assertNotNull($pluginEntityMock->getInstalledAt());
    }

    public function testInstallInClusterModeDoesNotTriggerComposer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setComposerName('MockPlugin');

        $this->container->setParameter('shopware.deployment.cluster_setup', true);

        $this->commandExecutor->expects(static::never())->method('require');
        $this->commandExecutor->expects(static::never())->method('remove')->with('MockPlugin');
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $this->pluginMock->expects(static::once())->method('install');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, Context::createDefaultContext());
    }

    public function testInstallThrowsErrorAndResetsComposer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setComposerName('MockPlugin');
        $context = Context::createDefaultContext();

        $this->commandExecutor->expects(static::once())->method('require')->with('MockPlugin:1.0.0');
        $this->commandExecutor->expects(static::once())->method('remove')->with('MockPlugin');
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $this->pluginMock->expects(static::once())->method('install')->willThrowException(new \Exception('not working'));

        static::expectException(\Exception::class);
        static::expectExceptionMessage('not working');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallUpgradeVersion(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setUpgradeVersion('9999999');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertNotNull($pluginEntityMock->getUpgradedAt());
    }

    public function testInstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->setComposerName('MockPlugin');

        $this->pluginServiceMock->expects(static::once())->method('refreshPlugins');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('get')->with(Plugin::class)->willReturn($this->pluginMock);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertCount(0, $this->eventDispatcher->getEvents());
    }

    public function testUninstallPlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('uninstall');

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreDeactivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostDeactivateEvent::class, $returnedEvents[1]);
        static::assertInstanceOf(PluginPreUninstallEvent::class, $returnedEvents[2]);
        static::assertInstanceOf(PluginPostUninstallEvent::class, $returnedEvents[3]);

        static::assertNull($pluginEntityMock->getInstalledAt());
        static::assertFalse($pluginEntityMock->getActive());
    }

    public function testUninstallPluginInClusterModeDoesNotTriggerComposer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $pluginEntityMock->setManagedByComposer(true);
        $pluginEntityMock->setComposerName('MockPlugin');

        $this->container->setParameter('shopware.deployment.cluster_setup', true);

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->commandExecutor->expects(static::never())->method('remove');

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('uninstall');

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setComposerName('MockPlugin');
        $pluginEntityMock->setActive(false);

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);

        $this->pluginServiceMock->expects(static::once())->method('refreshPlugins');

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUpdatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreUpdateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostUpdateEvent::class, $returnedEvents[1]);
    }

    public function testUpdatePluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->setComposerName('MockPlugin');

        $this->pluginServiceMock->expects(static::once())->method('refreshPlugins');

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginUpdateException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('update')->willThrowException(new \Exception('not working'));

        static::expectException(\Exception::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects(static::once())->method('activate');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreActivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostActivateEvent::class, $returnedEvents[1]);
        static::assertTrue($pluginEntityMock->getActive());
    }

    public function testActivatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginAlreadyActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
        static::assertCount(0, $this->eventDispatcher->getEvents());
    }

    public function testActivatePluginRebuildContainer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);

        $kernelMock->expects(static::once())->method('reboot');

        $this->container->set('kernel', $kernelMock);
        $this->container->set(Plugin\KernelPluginLoader\KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginRebuildContainerExceptionPath(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn(null);
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);

        $this->container->set('kernel', $kernelMock);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Container parameter "kernel.plugin_dir" needs to be a string');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginExceptionBootKernel(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $matcher = static::exactly(2);
        $kernelMock->expects($matcher)->method('getContainer')->willReturnCallback(function () use ($matcher, $containerMock): Container {
            if ($matcher->numberOfInvocations() === 1) {
                return $containerMock;
            }

            throw new \LogicException();
        });
        $this->container->set('kernel', $kernelMock);
        $this->container->set(Plugin\KernelPluginLoader\KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Failed to reboot the kernel');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    // ------ ActivatePlugin -----

    // +++++ DectivatePlugin method ++++

    public function testDeactivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects(static::once())->method('deactivate');

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertArrayHasKey('0', $returnedEvents);
        static::assertInstanceOf(PluginPreDeactivateEvent::class, $returnedEvents[0]);
        static::assertArrayHasKey('1', $returnedEvents);
        static::assertInstanceOf(PluginPostDeactivateEvent::class, $returnedEvents[1]);
        static::assertFalse($pluginEntityMock->getActive());
    }

    public function testDeactivatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginNotActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        static::expectException(PluginNotActivatedException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
        static::assertCount(0, $this->eventDispatcher->getEvents());
    }

    public function testDeactivatePluginDependants(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->requirementsValidatorMock
            ->expects(static::once())
            ->method('resolveActiveDependants')->willReturn([$this->pluginMock]);

        static::expectException(PluginHasActiveDependantsException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginRebuildContainer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);
        $this->container->set('kernel', $kernelMock);
        $this->container->set(Plugin\KernelPluginLoader\KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertCount(2, $this->eventDispatcher->getEvents());
    }

    public function testDeactivatePluginUpdateException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->pluginRepoMock->method('update')->willThrowException(new \Exception('failed update'));

        static::expectException(\Exception::class);
        static::expectExceptionMessage('failed update');

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    // ------ DeactivatePlugin -----

    // ++++++ privates +++++++

    public function testPluginBaseClassNotSet(): void
    {
        $pluginEntityMock = new PluginEntity();
        // @phpstan-ignore-next-line -> phpstan enforces correct base class strings
        $pluginEntityMock->setBaseClass('MockPlugin');
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        static::expectException(PluginBaseClassNotFoundException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testPluginMigrationCollection(): void
    {
        $pluginEntityMock = new PluginEntity();
        $pluginEntityMock->setId(Uuid::randomHex());
        $pluginEntityMock->setName('MockPlugin');
        $pluginEntityMock->setBaseClass(Plugin::class);
        $pluginEntityMock->setVersion('1.0.0');

        $pluginMock = $this->createMock(Plugin::class);
        $this->kernelPluginCollectionMock->method('get')->with(Plugin::class)->willReturn($pluginMock);
        $context = Context::createDefaultContext();

        $pluginMock->method('getPath')->willReturn('/');
        $pluginMock->method('getNamespace')->willReturn('');
        $pluginMock->method('getMigrationNamespace')->willReturn('');

        $migrationCollectionMock = $this->createMock(MigrationCollection::class);

        $migrationCollectionMock->expects(static::once())->method('sync');

        $this->migrationLoaderMock->expects(static::once())->method('addSource');
        $this->migrationLoaderMock->expects(static::once())->method('collect')->willReturn($migrationCollectionMock);

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testPluginGetPluginInstance(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->container->set(Plugin::class, $this->pluginMock);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertCount(2, $this->eventDispatcher->getEvents());
    }

    public function testPluginGetPluginInstanceException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->container->set(Plugin::class, new \ArrayObject());

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Shopware\Core\Framework\Plugin in the container should be an instance of Shopware\Core\Framework\Plugin');

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testPluginGetEntities(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('all')->willReturn([$this->pluginMock]);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertCount(2, $this->eventDispatcher->getEvents());
    }

    public function testOnResponseWithoutPluginMarkedForDelete(): void
    {
        $this->commandExecutor->expects(static::never())->method('remove');
        $this->pluginServiceMock->expects(static::never())->method('refreshPlugins');

        $this->pluginLifecycleService->onResponse();
    }

    public function testOnResponseWithPluginMarkedForDelete(): void
    {
        $context = Context::createDefaultContext();

        \Closure::bind(function () use ($context): void {
            $plugin = (new PluginEntity())->assign(['name' => 'MockPlugin', 'composerName' => 'MockPluginComposerName']);

            self::$pluginToBeDeleted = [
                'plugin' => $plugin,
                'context' => $context,
            ];
        }, $this->pluginLifecycleService, $this->pluginLifecycleService)();

        $this->commandExecutor->expects(static::once())
            ->method('remove')
            ->with('MockPluginComposerName', 'MockPlugin');

        $this->pluginServiceMock->expects(static::once())
            ->method('refreshPlugins')
            ->with($context);

        $this->pluginLifecycleService->onResponse();
    }

    private function getPluginEntityMock(): PluginEntity
    {
        $pluginEntity = new PluginEntity();
        $pluginEntity->setId(Uuid::randomHex());
        $pluginEntity->setName('MockPlugin');
        $pluginEntity->setBaseClass(Plugin::class);
        $pluginEntity->setVersion('1.0.0');

        $this->kernelPluginCollectionMock->method('get')->with(Plugin::class)->willReturn($this->pluginMock);

        return $pluginEntity;
    }
}

/**
 * @internal
 */
class FakeKernelPluginLoader extends Bundle
{
    /**
     * @param array<int, array<string, string|false>> $pluginInfos
     */
    public function __construct(private readonly array $pluginInfos)
    {
    }

    /**
     * @return array<int, array<string, string|false>>
     */
    public function getPluginInfos(): array
    {
        return $this->pluginInfos;
    }

    public function getClassLoader(): ClassLoader
    {
        return new ClassLoader();
    }
}
