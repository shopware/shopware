<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\Autoload\ClassLoader;
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
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
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
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Plugin\PluginLifecycleService
 */
class PluginLifecycleServiceTest extends TestCase
{
    private PluginLifecycleService $pluginLifecycleService;

    private MockObject&EntityRepository $pluginRepoMock;

    private MockObject&KernelPluginCollection $kernelPluginCollectionMock;

    private MockObject&Container $containerMock;

    private MockObject&MigrationCollectionLoader $migrationLoaderMock;

    private MockObject&RequirementsValidator $requirementsValidatorMock;

    private MockObject&CacheItemPoolInterface $cacheItemPoolInterfaceMock;

    private MockObject&Plugin $pluginMock;

    private MockObject&EventDispatcher $eventDispatcherMock;

    public function setUp(): void
    {
        $this->pluginRepoMock = $this->createMock(EntityRepository::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->kernelPluginCollectionMock = $this->createMock(KernelPluginCollection::class);
        $this->containerMock = $this->createMock(Container::class);
        $this->migrationLoaderMock = $this->createMock(MigrationCollectionLoader::class);
        $this->requirementsValidatorMock = $this->createMock(RequirementsValidator::class);
        $this->cacheItemPoolInterfaceMock = $this->createMock(CacheItemPoolInterface::class);
        $this->pluginMock = $this->createMock(Plugin::class);

        $this->pluginMock->method('getNamespace')->willReturn('MockPlugin');
        $this->pluginMock->method('getMigrationNamespace')->willReturn('migration');

        $this->pluginLifecycleService = new PluginLifecycleService(
            $this->pluginRepoMock,
            $this->eventDispatcherMock,
            $this->kernelPluginCollectionMock,
            $this->containerMock,
            $this->migrationLoaderMock,
            $this->createMock(AssetService::class),
            $this->createMock(CommandExecutor::class),
            $this->requirementsValidatorMock,
            $this->cacheItemPoolInterfaceMock,
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->createMock(SystemConfigService::class),
            $this->createMock(CustomEntityPersister::class),
            $this->createMock(CustomEntitySchemaUpdater::class),
            $this->createMock(CustomEntityLifecycleService::class),
        );
    }

    // +++++ InstallPlugin method ++++

    public function testInstallPlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $returnedEvents = [];

        $this->eventDispatcherMock->expects(static::exactly(2))->method('dispatch')
            ->willReturnCallback(
                function ($event) use (&$returnedEvents) {
                    $returnedEvents[] = $event;

                    return $event;
                }
            );

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('postInstall');

        /** InstalledAt is set */
        $pluginEntityMock->expects(static::once())->method('setInstalledAt');

        $installContext = $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
        static::assertInstanceOf(PluginPreInstallEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostInstallEvent::class, $returnedEvents[1]);
    }

    public function testInstallUpgradeVersion(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->expects(static::once())->method('getUpgradeVersion')->willReturn('9999999');

        /** setUpgradedAt is called */
        $pluginEntityMock->expects(static::once())->method('setUpgradedAt');

        $installContext = $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
    }

    public function testInstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn('MockPlugin');

        $installContext = $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
    }

    public function testInstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn(null);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $context = Context::createDefaultContext();

        /** will return before PluginPreInstallEvent is fired */
        $this->eventDispatcherMock->expects(static::never())->method('dispatch');

        $this->kernelPluginCollectionMock->method('get')->with('MockPlugin')->willReturn($this->pluginMock);

        $installContext = $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
    }

    // ------ InstallPlugin -----

    // +++++ UninstallPlugin method ++++

    public function testUninstallPlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $returnedEvents = [];

        $this->eventDispatcherMock->expects(static::exactly(4))->method('dispatch')
            ->willReturnCallback(
                function ($event) use (&$returnedEvents) {
                    $returnedEvents[] = $event;

                    return $event;
                }
            );

        $pluginEntityMock->expects(static::exactly(2))->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::exactly(2))->method('getActive')->willReturn(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('uninstall');

        $pluginEntityMock->expects(static::once())->method('setInstalledAt')->with(null);
        $pluginEntityMock->expects(static::exactly(2))->method('setActive')->with(false);

        $uninstallContext = $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $uninstallContext);
        static::assertInstanceOf(PluginPreDeactivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostDeactivateEvent::class, $returnedEvents[1]);
        static::assertInstanceOf(PluginPreUninstallEvent::class, $returnedEvents[2]);
        static::assertInstanceOf(PluginPostUninstallEvent::class, $returnedEvents[3]);
    }

    public function testUninstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn('MockPlugin');

        $installContext = $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
    }

    public function testUninstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn(null);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(null);
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    // ------ UninstallPlugin -----

    // +++++ UpdatePlugin method ++++

    public function testUpdatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $returnedEvents = [];

        $this->eventDispatcherMock->expects(static::exactly(2))->method('dispatch')
            ->willReturnCallback(
                function ($event) use (&$returnedEvents) {
                    $returnedEvents[] = $event;

                    return $event;
                }
            );

        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $updateContext = $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(UpdateContext::class, $updateContext);
        static::assertInstanceOf(PluginPreUpdateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostUpdateEvent::class, $returnedEvents[1]);
    }

    public function testUpdatePluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn('MockPlugin');

        $updateContext = $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(UpdateContext::class, $updateContext);
    }

    public function testUpdatePluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $this->pluginMock->expects(static::once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->expects(static::once())->method('getComposerName')->willReturn(null);

        static::expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(null);
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginUpdateException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::exactly(2))->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::exactly(2))->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();

        $this->pluginMock->expects(static::once())->method('update')->willThrowException(new \Exception('not working'));

        static::expectException(\Exception::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    // ------ UpdatePlugin -----

    // +++++ ActivatePlugin method ++++

    public function testActivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $returnedEvents = [];

        $this->eventDispatcherMock->expects(static::exactly(2))->method('dispatch')
            ->willReturnCallback(
                function ($event) use (&$returnedEvents) {
                    $returnedEvents[] = $event;

                    return $event;
                }
            );

        /** postInstall is called */
        $this->pluginMock->expects(static::once())->method('activate');

        /** InstalledAt is set */
        $pluginEntityMock->expects(static::once())->method('setActive')->with(true);

        $activateContext = $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(ActivateContext::class, $activateContext);
        static::assertInstanceOf(PluginPreActivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostActivateEvent::class, $returnedEvents[1]);
    }

    public function testActivatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(null);
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginAlreadyActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());
        $this->eventDispatcherMock->expects(static::never())->method('dispatch');

        $activateContext = $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(ActivateContext::class, $activateContext);
    }

    public function testActivatePluginRebuildContainer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcherMock);
        $kernelMock->method('getContainer')->willReturn($containerMock);
        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $kernelMock,
            new FakeKernelPluginLoader(
                [
                    [
                        'baseClass' => 'MockPlugin',
                        'active' => false,
                    ],
                ]
            )
        );

        $activateContext = $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(ActivateContext::class, $activateContext);
    }

    public function testActivatePluginRebuildContainerExceptionPath(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn(null);
        $containerMock->method('get')->willReturn($this->eventDispatcherMock);
        $kernelMock->method('getContainer')->willReturn($containerMock);
        $this->containerMock->method('get')->willReturn($kernelMock);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Container parameter "kernel.plugin_dir" needs to be a string');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginExceptionBootKernel(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcherMock);
        $matcher = static::exactly(2);
        $kernelMock->expects($matcher)->method('getContainer')->willReturnCallback(function () use ($matcher, $containerMock): Container {
            if ($matcher->getInvocationCount() === 1) {
                return $containerMock;
            }

            throw new \LogicException();
        });
        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $kernelMock,
            new FakeKernelPluginLoader(
                [
                    [
                        'baseClass' => 'MockPlugin',
                        'active' => false,
                    ],
                ]
            )
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Failed to reboot the kernel');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    // ------ ActivatePlugin -----

    // +++++ DectivatePlugin method ++++

    public function testDeactivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $returnedEvents = [];

        $this->eventDispatcherMock->expects(static::exactly(2))->method('dispatch')
            ->willReturnCallback(
                function ($event) use (&$returnedEvents) {
                    $returnedEvents[] = $event;

                    return $event;
                }
            );

        /** deactivate is called */
        $this->pluginMock->expects(static::once())->method('deactivate');

        /** InstalledAt is set */
        $pluginEntityMock->expects(static::once())->method('setActive')->with(false);

        $deactivateContext = $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(DeactivateContext::class, $deactivateContext);
        static::assertInstanceOf(PluginPreDeactivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostDeactivateEvent::class, $returnedEvents[1]);
    }

    public function testDeactivatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(null);
        $context = Context::createDefaultContext();

        static::expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginNotActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(false);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());
        $this->eventDispatcherMock->expects(static::never())->method('dispatch');

        static::expectException(PluginNotActivatedException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginDependants(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->requirementsValidatorMock->method('resolveActiveDependants')->willReturn([$this->pluginMock]);

        static::expectException(PluginHasActiveDependantsException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginRebuildContainer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = $this->createMock(Container::class);
        $containerMock->method('getParameter')->with('kernel.plugin_dir')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcherMock);
        $kernelMock->method('getContainer')->willReturn($containerMock);
        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $kernelMock,
            new FakeKernelPluginLoader(
                [
                    [
                        'baseClass' => 'MockPlugin',
                        'active' => false,
                    ],
                ]
            )
        );

        $deactivateContext = $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(DeactivateContext::class, $deactivateContext);
    }

    public function testDeactivatePluginUpdateException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);
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
        $pluginEntityMock = $this->createMock(PluginEntity::class);
        $pluginEntityMock->method('getBaseClass')->willReturn('MockPlugin');
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        static::expectException(PluginBaseClassNotFoundException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testPluginMigrationCollection(): void
    {
        $pluginEntityMock = $this->createMock(PluginEntity::class);
        $pluginEntityMock->method('getBaseClass')->willReturn('MockPlugin');
        $pluginMock = $this->createMock(Plugin::class);
        $this->kernelPluginCollectionMock->method('get')->with('MockPlugin')->willReturn($pluginMock);
        $context = Context::createDefaultContext();

        $pluginMock->method('getPath')->willReturn('/');
        $pluginMock->method('getNamespace')->willReturn('');
        $pluginMock->method('getMigrationNamespace')->willReturn('');

        $migrationCollectionMock = $this->createMock(MigrationCollection::class);

        $migrationCollectionMock->expects(static::once())->method('sync');

        $this->migrationLoaderMock->expects(static::once())->method('addSource');
        $this->migrationLoaderMock->expects(static::once())->method('collect')->willReturn($migrationCollectionMock);

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        $installContext = $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertInstanceOf(InstallContext::class, $installContext);
    }

    public function testPluginGetPluginInstance(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();

        $this->containerMock->method('has')->with('MockPlugin')->willReturn(true);
        $this->containerMock->method('get')->with('MockPlugin')->willReturn($this->pluginMock);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $deactivateContext = $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(DeactivateContext::class, $deactivateContext);
    }

    public function testPluginGetPluginInstanceException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();

        $this->containerMock->method('has')->with('MockPlugin')->willReturn(true);
        $this->containerMock->method('get')->with('MockPlugin')->willReturn(new \ArrayObject());

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('MockPlugin in the container should be an instance of Shopware\Core\Framework\Plugin');

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testPluginGetEntities(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->expects(static::once())->method('getInstalledAt')->willReturn(new \DateTime());
        $pluginEntityMock->expects(static::once())->method('getActive')->willReturn(true);
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('all')->willReturn([$this->pluginMock]);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $deactivateContext = $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertInstanceOf(DeactivateContext::class, $deactivateContext);
    }

    // ------ privates -------

    /**
     * @return MockObject&PluginEntity
     */
    private function getPluginEntityMock(): PluginEntity
    {
        $pluginEntityMock = $this->createMock(PluginEntity::class);
        $pluginEntityMock->method('getBaseClass')->willReturn('MockPlugin');

        $this->kernelPluginCollectionMock->method('get')->with('MockPlugin')->willReturn($this->pluginMock);

        return $pluginEntityMock;
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
