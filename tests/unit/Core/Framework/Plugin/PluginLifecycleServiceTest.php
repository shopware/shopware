<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\Xml\CustomFields;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginLifecycleService::class)]
class PluginLifecycleServiceTest extends TestCase
{
    private PluginLifecycleService $pluginLifecycleService;

    /**
     * @var Stub&EntityRepository<PluginCollection>
     */
    private Stub&EntityRepository $pluginRepoMock;

    private Stub&KernelPluginCollection $kernelPluginCollectionMock;

    private Container $container;

    private Stub&MigrationCollectionLoader $migrationLoaderMock;

    private Stub&RequirementsValidator $requirementsValidatorMock;

    private Stub&CacheItemPoolInterface $cacheItemPoolInterfaceMock;

    private MockObject&Plugin $pluginMock;

    private CollectingEventDispatcher $eventDispatcher;

    private Stub&PluginService $pluginServiceMock;

    private CommandExecutor&Stub $commandExecutor;

    private RequestStack&Stub $requestStackMock;

    private CustomFieldSetPersister&Stub $customFieldSetPersister;

    protected function setUp(): void
    {
        $this->pluginRepoMock = static::createStub(EntityRepository::class);
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->kernelPluginCollectionMock = static::createStub(KernelPluginCollection::class);
        $this->container = new ContainerBuilder();
        $this->migrationLoaderMock = static::createStub(MigrationCollectionLoader::class);
        $this->requirementsValidatorMock = static::createStub(RequirementsValidator::class);
        $this->cacheItemPoolInterfaceMock = static::createStub(CacheItemPoolInterface::class);
        $this->pluginServiceMock = static::createStub(PluginService::class);
        $this->commandExecutor = static::createStub(CommandExecutor::class);

        $this->container->setParameter('shopware.deployment.cluster_setup', false);

        $this->pluginMock = $this->createMock(Plugin::class);

        $this->pluginMock->method('getNamespace')->willReturn('MockPlugin');
        $this->pluginMock->method('getMigrationNamespace')->willReturn('migration');

        $this->requestStackMock = static::createStub(RequestStack::class);
        $this->customFieldSetPersister = static::createStub(CustomFieldSetPersister::class);

        $this->pluginLifecycleService = $this->createService();
    }

    protected function tearDown(): void
    {
        // uninstallPlugin() populates PluginLifecycleService::$pluginToBeDeleted; reset just that
        // one static so it doesn't leak into other tests. Do NOT use #[BackupStaticProperties(true)]:
        // it serialize-restores every loaded class's statics, which desyncs Doctrine's global
        // Type registry (spl_object_id-keyed reverse index) and breaks Type::lookupName() worker-wide.
        (new \ReflectionClass(PluginLifecycleService::class))->setStaticPropertyValue('pluginToBeDeleted', null);
    }

    public function testInstallPlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->once())->method('postInstall');

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

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->never())->method('require');
        $commandExecutor->expects($this->never())->method('remove');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor);

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);
        $this->pluginMock->expects($this->once())->method('install');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, Context::createDefaultContext());
    }

    public function testInstallThrowsErrorAndResetsComposer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setComposerName('MockPlugin');
        $context = Context::createDefaultContext();

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->once())->method('require')->with('MockPlugin:1.0.0');
        $commandExecutor->expects($this->once())->method('remove')->with('MockPlugin');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor);

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);
        $this->pluginMock->expects($this->once())->method('install')->willThrowException(new \Exception('not working'));

        $this->expectExceptionObject(new \Exception('not working'));

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallUpgradeVersion(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setUpgradeVersion('9999999');

        $this->pluginMock->expects($this->once())->method('install');

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);

        static::assertNotNull($pluginEntityMock->getUpgradedAt());
    }

    public function testInstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->setComposerName('MockPlugin');

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('refreshPlugins');
        $this->pluginLifecycleService = $this->createService(pluginService: $pluginService);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);

        $this->expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('get')->willReturnMap([[Plugin::class, $this->pluginMock]]);

        $this->pluginMock->expects($this->never())->method('install');

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
        $this->pluginMock->expects($this->once())->method('uninstall');

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

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->never())->method('remove');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor);

        /** postInstall is called */
        $this->pluginMock->expects($this->once())->method('uninstall');

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginMajor(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setComposerName('MockPlugin');
        $pluginEntityMock->setActive(false);

        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('refreshPlugins');
        $this->pluginLifecycleService = $this->createService(pluginService: $pluginService);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);

        $this->expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginRemovesCustomFieldsByExtensionName(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $extensionName = $this->pluginMock->getName();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $this->pluginMock->expects($this->once())->method('uninstall');

        $customFieldSetPersister = $this->createMock(CustomFieldSetPersister::class);
        $customFieldSetPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(static fn (CustomFields $customFields): bool => $customFields->getCustomFieldSets() === []),
                null,
                $extensionName,
                $context
            );
        $this->pluginLifecycleService = $this->createService(customFieldSetPersister: $customFieldSetPersister);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUninstallPluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->never())->method('uninstall');

        $this->expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->uninstallPlugin($pluginEntityMock, $context);
    }

    public function testUpdatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects($this->once())->method('update');

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
        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);
        $pluginEntityMock->setComposerName('MockPlugin');

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('refreshPlugins');
        $this->pluginLifecycleService = $this->createService(pluginService: $pluginService);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginMajorComposerException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $pluginEntityMock->setInstalledAt(new \DateTime());
        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);

        $this->expectException(PluginComposerJsonInvalidException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->never())->method('update');

        $this->expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginUpdateException(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->once())->method('update')->willThrowException(new \Exception('not working'));

        $this->expectException(\Exception::class);

        $this->pluginLifecycleService->updatePlugin($pluginEntityMock, $context);
    }

    public function testUpdatePluginWithComposerCommandExecutionDisabledAfterUpdate(): void
    {
        $plugin = $this->getPluginEntityMock();
        $plugin->setInstalledAt(new \DateTime());
        $plugin->setActive(true);
        $plugin->setUpgradeVersion('1.0.1');
        $plugin->setManagedByComposer(true);
        $plugin->setComposerName('swag/mock-plugin');
        $plugin->setPath('custom/plugins/mock-plugin');

        $this->pluginMock->expects($this->once())->method('executeComposerCommands');

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->once())->method('remove');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor);

        $this->pluginLifecycleService->updatePlugin($plugin, Context::createDefaultContext());
    }

    public function testUpdatePluginSyncsEmptyCustomFieldsWhenXmlFileIsMissing(): void
    {
        $plugin = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();
        $extensionName = $this->pluginMock->getName();

        $plugin->setInstalledAt(new \DateTime());
        $plugin->setActive(false);

        $this->pluginMock->expects($this->once())->method('update');

        $customFieldSetPersister = $this->createMock(CustomFieldSetPersister::class);
        $customFieldSetPersister->expects($this->once())
            ->method('sync')
            ->with(
                static::callback(static fn (CustomFields $customFields): bool => $customFields->getCustomFieldSets() === []),
                null,
                $extensionName,
                $context
            );
        $this->pluginLifecycleService = $this->createService(customFieldSetPersister: $customFieldSetPersister);

        $this->pluginLifecycleService->updatePlugin($plugin, $context);
    }

    public function testUninstallPluginWithComposerCommandExecutionDisabledAfterUpdateWithoutCli(): void
    {
        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->never())->method('remove');

        $pluginLifecycleService = $this->getMockBuilder(PluginLifecycleService::class)
            ->setConstructorArgs([
                $this->pluginRepoMock,
                $this->eventDispatcher,
                $this->kernelPluginCollectionMock,
                $this->container,
                $this->migrationLoaderMock,
                static::createStub(AssetService::class),
                $commandExecutor,
                $this->requirementsValidatorMock,
                $this->cacheItemPoolInterfaceMock,
                Kernel::SHOPWARE_FALLBACK_VERSION,
                static::createStub(SystemConfigService::class),
                static::createStub(CustomEntityPersister::class),
                static::createStub(CustomEntitySchemaUpdater::class),
                $this->pluginServiceMock,
                static::createStub(VersionSanitizer::class),
                static::createStub(DefinitionInstanceRegistry::class),
                $this->requestStackMock,
                static::createStub(CustomFieldSetPersister::class),
                new MockClock(),
            ])
            ->onlyMethods(['isCLI'])
            ->getMock();

        $pluginLifecycleService->expects($this->once())->method('isCLI')->willReturn(false);

        $plugin = $this->getPluginEntityMock();
        $plugin->setInstalledAt(new \DateTime());
        $plugin->setActive(true);
        $plugin->setUpgradeVersion('1.0.1');
        $plugin->setManagedByComposer(true);
        $plugin->setComposerName('swag/mock-plugin');
        $plugin->setPath('custom/plugins/mock-plugin');

        $this->pluginMock->expects($this->once())->method('rebuildContainer')->willReturn(true);
        $this->pluginMock->expects($this->once())->method('executeComposerCommands')->willReturn(true);
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getContainer')->willReturn($this->container);
        $this->container->set('kernel', $kernel);
        $this->container->setParameter('kernel.plugin_dir', 'custom/plugins');
        $this->container->set(KernelPluginLoader::class, static::createStub(KernelPluginLoader::class));

        // mock replaced event dispatcher like it is during plugin deactivation & kernel reboot
        $replacedEventDispatcher = new CollectingEventDispatcher();
        $this->container->set('event_dispatcher', $replacedEventDispatcher);

        $pluginLifecycleService->uninstallPlugin($plugin, Context::createDefaultContext());

        static::assertEmpty($replacedEventDispatcher->getListeners());
        static::assertCount(1, $this->eventDispatcher->getListeners());
    }

    public function testUpdatePluginWithComposerCommandExecutionDisabledAfterUpdateButInstalledViaComposerDirectly(): void
    {
        $plugin = $this->getPluginEntityMock();
        $plugin->setInstalledAt(new \DateTime());
        $plugin->setActive(true);
        $plugin->setUpgradeVersion('1.0.1');
        $plugin->setManagedByComposer(true);
        $plugin->setComposerName('swag/mock-plugin');
        $plugin->setPath('vendor/shopware/mock-plugin');

        $this->pluginMock->expects($this->once())->method('executeComposerCommands');

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->never())->method('remove');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor);

        $this->pluginLifecycleService->updatePlugin($plugin, Context::createDefaultContext());
    }

    public function testActivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $requirementsValidator = $this->createMock(RequirementsValidator::class);
        $requirementsValidator->expects($this->once())
            ->method('validateRequirements')
            ->with($pluginEntityMock, $context, PluginLifecycleService::PLUGIN_LIFECYCLE_METHOD_ACTIVATE);
        $this->pluginLifecycleService = $this->createService(requirementsValidator: $requirementsValidator);

        $this->pluginMock->expects($this->once())->method('activate');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreActivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostActivateEvent::class, $returnedEvents[1]);
        static::assertTrue($pluginEntityMock->getActive());
    }

    public function testActivatePluginWithoutValidatingRequirements(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $requirementsValidator = $this->createMock(RequirementsValidator::class);
        $requirementsValidator->expects($this->never())
            ->method('validateRequirements');
        $this->pluginLifecycleService = $this->createService(requirementsValidator: $requirementsValidator);

        $this->pluginMock->expects($this->once())->method('activate');

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context, validateRequirements: false);

        $returnedEvents = $this->eventDispatcher->getEvents();

        static::assertInstanceOf(PluginPreActivateEvent::class, $returnedEvents[0]);
        static::assertInstanceOf(PluginPostActivateEvent::class, $returnedEvents[1]);
        static::assertTrue($pluginEntityMock->getActive());
    }

    public function testActivatePluginRollsBackActiveStateWhenPostActivateEventFails(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);

        $context = Context::createDefaultContext();
        $exception = new \RuntimeException('Post activate failed');

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects($this->once())->method('activate');

        $pluginRepo = $this->createMock(EntityRepository::class);
        $this->pluginLifecycleService = $this->createService(pluginRepo: $pluginRepo);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatchMatcher = $this->exactly(2);
        $eventDispatcher->expects($eventDispatchMatcher)
            ->method('dispatch')
            ->willReturnCallback(static function (object $event, ?string $eventName = null) use ($eventDispatchMatcher, $exception): object {
                static::assertNull($eventName);

                if ($eventDispatchMatcher->numberOfInvocations() === 1) {
                    static::assertInstanceOf(PluginPreActivateEvent::class, $event);

                    return $event;
                }

                static::assertInstanceOf(PluginPostActivateEvent::class, $event);

                throw $exception;
            });

        (new \ReflectionProperty(PluginLifecycleService::class, 'eventDispatcher'))->setValue($this->pluginLifecycleService, $eventDispatcher);

        $repoUpdateMatcher = $this->exactly(2);
        $pluginRepo->expects($repoUpdateMatcher)
            ->method('update')
            ->willReturnCallback(static function (array $data, Context $actualContext) use ($repoUpdateMatcher, $pluginEntityMock, $context): EntityWrittenContainerEvent {
                static::assertSame($context, $actualContext);
                static::assertSame(
                    [
                        [
                            'id' => $pluginEntityMock->getId(),
                            'active' => $repoUpdateMatcher->numberOfInvocations() === 1,
                        ],
                    ],
                    $data
                );

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $actualContext, []);
            });

        try {
            $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
            static::fail('Expected post activate failure to be rethrown.');
        } catch (\RuntimeException $actualException) {
            static::assertSame($exception, $actualException);
        }

        static::assertFalse($pluginEntityMock->getActive());
    }

    public function testActivatePluginNotInstalled(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->never())->method('activate');

        $this->expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testActivatePluginAlreadyActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects($this->never())->method('activate');

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
        $containerMock = static::createStub(Container::class);
        $containerMock->method('getParameter')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);

        $kernelMock->expects($this->once())->method('reboot');

        $this->pluginMock->expects($this->once())->method('rebuildContainer');

        $this->container->set('kernel', $kernelMock);
        $this->container->set(KernelPluginLoader::class, new FakeKernelPluginLoader(
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

        $kernelMock = static::createStub(Kernel::class);
        $containerMock = static::createStub(Container::class);
        $containerMock->method('getParameter')->willReturn(null);
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);

        $this->container->set('kernel', $kernelMock);

        $this->pluginMock->expects($this->never())->method('activate');

        $this->expectExceptionObject(PluginException::invalidContainerParameter('kernel.plugin_dir', 'string'));

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
        $containerMock = static::createStub(Container::class);
        $containerMock->method('getParameter')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $matcher = $this->exactly(2);
        $kernelMock->expects($matcher)->method('getContainer')->willReturnCallback(static function () use ($matcher, $containerMock): Container {
            if ($matcher->numberOfInvocations() === 1) {
                return $containerMock;
            }

            throw new \LogicException();
        });
        $this->container->set('kernel', $kernelMock);
        $this->container->set(KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        $this->pluginMock->expects($this->never())->method('activate');

        $this->expectExceptionObject(new \RuntimeException('Failed to reboot the kernel'));

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePlugin(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext();

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects($this->once())->method('deactivate');

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

        $this->pluginMock->expects($this->never())->method('deactivate');

        $this->expectException(PluginNotInstalledException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginNotActive(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);
        $context = Context::createDefaultContext();
        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $this->pluginMock->expects($this->never())->method('deactivate');

        $this->expectException(PluginNotActivatedException::class);

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

        $requirementsValidator = $this->createMock(RequirementsValidator::class);
        $requirementsValidator
            ->expects($this->once())
            ->method('resolveActiveDependants')->willReturn([$this->pluginMock]);
        $this->pluginLifecycleService = $this->createService(requirementsValidator: $requirementsValidator);

        $this->pluginMock->expects($this->never())->method('deactivate');

        $this->expectException(PluginHasActiveDependantsException::class);

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testDeactivatePluginRebuildContainer(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(true);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = static::createStub(Kernel::class);
        $containerMock = static::createStub(Container::class);
        $containerMock->method('getParameter')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);
        $this->container->set('kernel', $kernelMock);
        $this->container->set(KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        $this->pluginMock->expects($this->once())->method('deactivate');

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

        $this->pluginMock->expects($this->once())->method('deactivate');

        $this->expectExceptionObject(new \Exception('failed update'));

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);
    }

    public function testPluginBaseClassNotSet(): void
    {
        $pluginEntityMock = new PluginEntity();
        $pluginEntityMock->setBaseClass(Plugin::class);
        $context = Context::createDefaultContext();

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        $this->pluginMock->expects($this->never())->method('install');

        $this->expectException(PluginBaseClassNotFoundException::class);

        $this->pluginLifecycleService->installPlugin($pluginEntityMock, $context);
    }

    public function testPluginMigrationCollection(): void
    {
        $pluginEntityMock = new PluginEntity();
        $pluginEntityMock->setId(Uuid::randomHex());
        $pluginEntityMock->setName('MockPlugin');
        $pluginEntityMock->setBaseClass(Plugin::class);
        $pluginEntityMock->setVersion('1.0.0');

        $pluginMock = static::createStub(Plugin::class);
        $this->kernelPluginCollectionMock->method('get')->willReturnMap([[Plugin::class, $pluginMock]]);
        $context = Context::createDefaultContext();

        $pluginMock->method('getPath')->willReturn('/');
        $pluginMock->method('getNamespace')->willReturn('');
        $pluginMock->method('getMigrationNamespace')->willReturn('');

        $migrationCollectionMock = $this->createMock(MigrationCollection::class);

        $migrationCollectionMock->expects($this->once())->method('sync');

        $migrationLoader = $this->createMock(MigrationCollectionLoader::class);
        $migrationLoader->expects($this->once())->method('addSource');
        $migrationLoader->expects($this->once())->method('collect')->willReturn($migrationCollectionMock);
        $this->pluginLifecycleService = $this->createService(migrationLoader: $migrationLoader);

        $this->kernelPluginCollectionMock->method('get')->willReturn(null);

        // the lifecycle installs the local plugin stub above, never the shared plugin mock
        $this->pluginMock->expects($this->never())->method('install');

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

        $this->pluginMock->expects($this->once())->method('deactivate');

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

        $this->pluginMock->expects($this->never())->method('deactivate');

        $this->expectExceptionObject(PluginException::wrongBaseClass(Plugin::class));

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

        $this->pluginMock->expects($this->once())->method('deactivate');

        $this->pluginLifecycleService->deactivatePlugin($pluginEntityMock, $context);

        static::assertCount(2, $this->eventDispatcher->getEvents());
    }

    public function testOnResponseWithoutPluginMarkedForDelete(): void
    {
        $this->pluginMock->expects($this->never())->method('executeComposerCommands');

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->never())->method('remove');
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->never())->method('refreshPlugins');
        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor, pluginService: $pluginService);

        $this->pluginLifecycleService->onResponse();
    }

    public function testOnResponseWithPluginMarkedForDelete(): void
    {
        $context = Context::createDefaultContext();

        $this->pluginMock->expects($this->never())->method('executeComposerCommands');

        $commandExecutor = $this->createMock(CommandExecutor::class);
        $commandExecutor->expects($this->once())
            ->method('remove')
            ->with('MockPluginComposerName', 'MockPlugin');

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())
            ->method('refreshPlugins')
            ->with($context);

        $this->pluginLifecycleService = $this->createService(commandExecutor: $commandExecutor, pluginService: $pluginService);

        // Do not declare closure as static
        \Closure::bind(function () use ($context): void {
            $plugin = (new PluginEntity())->assign(['name' => 'MockPlugin', 'composerName' => 'MockPluginComposerName']);

            self::$pluginToBeDeleted = [
                'plugin' => $plugin,
                'context' => $context,
            ];
        }, $this->pluginLifecycleService, $this->pluginLifecycleService)();

        $this->pluginLifecycleService->onResponse();
    }

    public function testActivatePluginClosesSession(): void
    {
        $pluginEntityMock = $this->getPluginEntityMock();
        $pluginEntityMock->setInstalledAt(new \DateTime());
        $pluginEntityMock->setActive(false);
        $context = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));

        $this->cacheItemPoolInterfaceMock->method('getItem')->willReturn(new CacheItem());

        $kernelMock = $this->createMock(Kernel::class);
        $containerMock = static::createStub(Container::class);
        $containerMock->method('getParameter')->willReturn('tmp');
        $containerMock->method('get')->willReturn($this->eventDispatcher);
        $kernelMock->method('getContainer')->willReturn($containerMock);

        $this->container->set('kernel', $kernelMock);
        $this->container->set(KernelPluginLoader::class, new FakeKernelPluginLoader(
            [
                [
                    'baseClass' => Plugin::class,
                    'active' => false,
                ],
            ]
        ));

        $this->pluginMock->expects($this->once())->method('activate');

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('isStarted')->willReturn(true);

        $request = new Request();
        $request->setSession($sessionMock);
        $this->requestStackMock->method('getCurrentRequest')->willReturn($request);

        // Validate that session is saved (to release session locks) before kernel reboot (long operation)
        $sessionSaved = false;
        $sessionMock->expects($this->once())->method('save')->willReturnCallback(static function () use (&$sessionSaved): void {
            $sessionSaved = true;
        });
        $kernelMock->expects($this->once())->method('reboot')->willReturnCallback(static function () use (&$sessionSaved): void {
            static::assertTrue($sessionSaved, 'Session must be saved before kernel reboot to prevent session lock issues');
        });

        $this->pluginLifecycleService->activatePlugin($pluginEntityMock, $context);
    }

    /**
     * Builds the SUT from the shared setUp doubles. A test that needs to assert calls on one of the
     * mixed collaborators creates a local mock and passes it here, rebuilding the SUT with that mock
     * in place while keeping the other collaborators as the shared stubs.
     *
     * @param EntityRepository<PluginCollection>|null $pluginRepo
     */
    private function createService(
        ?EntityRepository $pluginRepo = null,
        ?MigrationCollectionLoader $migrationLoader = null,
        ?CommandExecutor $commandExecutor = null,
        ?RequirementsValidator $requirementsValidator = null,
        ?PluginService $pluginService = null,
        ?CustomFieldSetPersister $customFieldSetPersister = null,
    ): PluginLifecycleService {
        return new PluginLifecycleService(
            $pluginRepo ?? $this->pluginRepoMock,
            $this->eventDispatcher,
            $this->kernelPluginCollectionMock,
            $this->container,
            $migrationLoader ?? $this->migrationLoaderMock,
            static::createStub(AssetService::class),
            $commandExecutor ?? $this->commandExecutor,
            $requirementsValidator ?? $this->requirementsValidatorMock,
            $this->cacheItemPoolInterfaceMock,
            Kernel::SHOPWARE_FALLBACK_VERSION,
            static::createStub(SystemConfigService::class),
            static::createStub(CustomEntityPersister::class),
            static::createStub(CustomEntitySchemaUpdater::class),
            $pluginService ?? $this->pluginServiceMock,
            static::createStub(VersionSanitizer::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $this->requestStackMock,
            $customFieldSetPersister ?? $this->customFieldSetPersister,
            new NativeClock()
        );
    }

    private function getPluginEntityMock(): PluginEntity
    {
        $pluginEntity = new PluginEntity();
        $pluginEntity->setId(Uuid::randomHex());
        $pluginEntity->setName('MockPlugin');
        $pluginEntity->setBaseClass(Plugin::class);
        $pluginEntity->setVersion('1.0.0');
        $pluginEntity->setManagedByComposer(false);

        $this->kernelPluginCollectionMock->method('get')->willReturnMap([[Plugin::class, $this->pluginMock]]);

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
