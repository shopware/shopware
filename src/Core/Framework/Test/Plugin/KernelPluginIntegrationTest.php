<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Google\Auth\Cache\MemoryCacheItemPool;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SwagTest\SwagTest;

/**
 * @group slow
 * @group skip-paratest
 */
class KernelPluginIntegrationTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    /**
     * @var Kernel|null
     */
    private $kernel;

    public function tearDown(): void
    {
        if ($this->kernel) {
            $this->kernel->getContainer()
                ->get('test.service_container')
                ->get('cache.object')
                ->clear();
        }
    }

    public function testWithDisabledPlugins(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        $loader = new StaticKernelPluginLoader($this->classLoader);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        static::assertEmpty($this->kernel->getPluginLoader()->getPluginInstances()->all());
    }

    public function testInactive(): void
    {
        $this->insertPlugin($this->getInstalledInactivePlugin());

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $plugins = $this->kernel->getPluginLoader()->getPluginInstances();
        static::assertNotEmpty($plugins->all());

        $testPlugin = $plugins->get(SwagTest::class);
        static::assertNotNull($testPlugin);

        static::assertFalse($testPlugin->isActive());
    }

    public function testActive(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        $this->connection->executeUpdate('UPDATE plugin SET active = 1, installed_at = date(now())');

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $testPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get(SwagTest::class);
        static::assertNotNull($testPlugin);

        static::assertTrue($testPlugin->isActive());
    }

    public function testInactiveDefinitionsNotLoaded(): void
    {
        $this->insertPlugin($this->getInstalledInactivePlugin());

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        static::assertFalse($this->kernel->getContainer()->has(SwagTest::class));
    }

    public function testActiveAutoLoadedAndWired(): void
    {
        $this->insertPlugin($this->getActivePlugin());

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        // should always be public
        static::assertTrue($this->kernel->getContainer()->has(SwagTest::class));

        $swagTestPlugin = $this->kernel->getContainer()->get(SwagTest::class);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $swagTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('category.repository'), $swagTestPlugin->categoryRepository);
    }

    public function testActivate(): void
    {
        $inactive = $this->getInstalledInactivePlugin();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext());

        $swagTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertNotNull($swagTestPlugin);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $swagTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('category.repository'), $swagTestPlugin->categoryRepository);

        // the plugin services are still not loaded when the preActivate fires but in the postActivateContext event
        static::assertNull($swagTestPlugin->preActivateContext);
        static::assertNotNull($swagTestPlugin->postActivateContext);
        static::assertNull($swagTestPlugin->preDeactivateContext);
        static::assertNull($swagTestPlugin->postDeactivateContext);
    }

    public function testActivateWithoutRebuildWithSystemSource(): void
    {
        $inactive = $this->getInstalledInactivePluginRebuildDisabled();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext());

        $swagTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertNotNull($swagTestPlugin);

        // not autowired
        static::assertNull($swagTestPlugin->systemConfig);

        // not set
        static::assertNull($swagTestPlugin->categoryRepository);

        // the plugin services are still not loaded
        static::assertNull($swagTestPlugin->preActivateContext);
        static::assertNull($swagTestPlugin->postActivateContext);
        static::assertNull($swagTestPlugin->preDeactivateContext);
        static::assertNull($swagTestPlugin->postDeactivateContext);
    }

    public function testActivateWithoutRebuildWithNonSystemContext(): void
    {
        $inactive = $this->getInstalledInactivePluginRebuildDisabled();
        $this->insertPlugin($inactive);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();
        $lifecycleService->activatePlugin($inactive, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));

        $swagTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($inactive->getBaseClass());
        static::assertNotNull($swagTestPlugin);

        // autowired
        static::assertInstanceOf(SystemConfigService::class, $swagTestPlugin->systemConfig);

        // manually set
        static::assertSame($this->kernel->getContainer()->get('category.repository'), $swagTestPlugin->categoryRepository);

        // the plugin services are still not loaded when the preActivate fires but in the postActivateContext event
        static::assertNull($swagTestPlugin->preActivateContext);
        static::assertNotNull($swagTestPlugin->postActivateContext);
        static::assertNull($swagTestPlugin->preDeactivateContext);
        static::assertNull($swagTestPlugin->postDeactivateContext);
    }

    public function testDeactivate(): void
    {
        $active = $this->getActivePlugin();
        $this->insertPlugin($active);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $lifecycleService = $this->makePluginLifecycleService();

        $oldPluginInstance = $this->kernel->getPluginLoader()->getPluginInstances()->get($active->getBaseClass());

        $lifecycleService->deactivatePlugin($active, Context::createDefaultContext());

        $swagTestPlugin = $this->kernel->getPluginLoader()->getPluginInstances()->get($active->getBaseClass());

        // only the preDeactivate is called with the plugin still active
        static::assertNull($oldPluginInstance->preActivateContext);
        static::assertNull($oldPluginInstance->postActivateContext);
        static::assertNotNull($oldPluginInstance->preDeactivateContext);
        static::assertNull($oldPluginInstance->postDeactivateContext);

        // no plugin service should be loaded after deactivating it
        static::assertNull($swagTestPlugin->systemConfig);
        static::assertNull($swagTestPlugin->categoryRepository);

        static::assertNull($swagTestPlugin->preActivateContext);
        static::assertNull($swagTestPlugin->postActivateContext);
        static::assertNull($swagTestPlugin->preDeactivateContext);
        static::assertNull($swagTestPlugin->postDeactivateContext);
    }

    public function testKernelParameters(): void
    {
        $plugin = $this->getInstalledInactivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->kernel = $this->makeKernel($loader);
        $this->kernel->boot();

        $expectedParameters = [
            'kernel.shopware_version' => self::getTestVersion(),
            'kernel.shopware_version_revision' => self::getTestRevision(),
            'kernel.project_dir' => TEST_PROJECT_DIR,
            'kernel.plugin_dir' => TEST_PROJECT_DIR . '/custom/plugins',
        ];

        $actualParameters = [];
        foreach ($expectedParameters as $key => $_value) {
            $actualParameters[$key] = $this->kernel->getContainer()->getParameter($key);
        }

        static::assertSame($expectedParameters, $actualParameters);

        $lifecycleService = $this->makePluginLifecycleService();

        $lifecycleService->activatePlugin($plugin, Context::createDefaultContext());

        $newActualParameters = [];
        foreach ($expectedParameters as $key => $_value) {
            $newActualParameters[$key] = $this->kernel->getContainer()->getParameter($key);
        }

        $activePlugins = $this->kernel->getContainer()->getParameter('kernel.active_plugins');

        static::assertIsArray($activePlugins);
        static::assertArrayHasKey(SwagTest::class, $activePlugins);

        static::assertArrayHasKey('name', $activePlugins[SwagTest::class]);
        static::assertArrayHasKey('path', $activePlugins[SwagTest::class]);
        static::assertArrayHasKey('class', $activePlugins[SwagTest::class]);

        static::assertSame($expectedParameters, $newActualParameters);
    }

    public function testScheduledTaskIsRegisteredOnPluginStateChange(): void
    {
        $plugin = $this->getInstalledInactivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, $this->connection);
        $this->makeKernel($loader);
        $this->kernel->boot();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'swag_test.test_task'));

        $context = Context::createDefaultContext();

        /** @var EntityRepositoryInterface $scheduledTasksRepo */
        $scheduledTasksRepo = $this->kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities()->first();
        static::assertNull($result);

        $pluginLifecycleManager = $this->makePluginLifecycleService();
        $pluginLifecycleManager->activatePlugin($plugin, $context);

        /** @var EntityRepositoryInterface $scheduledTasksRepo */
        $scheduledTasksRepo = $this->kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities();
        static::assertNotNull($result);

        $pluginLifecycleManager->deactivatePlugin($plugin, $context);

        /** @var EntityRepositoryInterface $scheduledTasksRepo */
        $scheduledTasksRepo = $this->kernel->getContainer()->get('scheduled_task.repository');
        $result = $scheduledTasksRepo->search($criteria, $context)->getEntities()->first();
        static::assertNull($result);
    }

    private function makePluginLifecycleService(): PluginLifecycleService
    {
        $container = $this->kernel->getContainer();

        $emptyPluginCollection = new PluginCollection();
        $pluginRepoMock = $this->createMock(EntityRepositoryInterface::class);

        $pluginRepoMock
            ->method('search')
            ->willReturn(new EntitySearchResult('plugin', 0, $emptyPluginCollection, null, new Criteria(), Context::createDefaultContext()));

        return new PluginLifecycleService(
            $pluginRepoMock,
            $container->get('event_dispatcher'),
            $this->kernel->getPluginLoader()->getPluginInstances(),
            $container,
            $this->createMock(MigrationCollectionLoader::class),
            $this->createMock(AssetService::class),
            $this->createMock(CommandExecutor::class),
            $this->createMock(RequirementsValidator::class),
            new MemoryCacheItemPool(),
            $container->getParameter('kernel.shopware_version'),
            $container->get(SystemConfigService::class)
        );
    }

    private function makeKernel(KernelPluginLoader $loader): Kernel
    {
        $kernelClass = KernelLifecycleManager::getKernelClass();
        $version = 'v' . self::getTestVersion() . '@' . self::getTestRevision();
        $this->kernel = new $kernelClass('test', true, $loader, Uuid::randomHex(), $version);
        $connection = (new \ReflectionClass($kernelClass))->getProperty('connection');
        $connection->setAccessible(true);
        $connection->setValue($this->connection);

        return $this->kernel;
    }

    private static function getTestRevision(): string
    {
        return md5('test');
    }

    private static function getTestVersion(): string
    {
        return '6.3.0.0';
    }
}
