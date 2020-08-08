<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SwagTest\Migration\Migration1536761533Test;
use SwagTest\SwagTest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class PluginLifecycleServiceTest extends TestCase
{
    use PluginTestsHelper;
    use MigrationTestBehaviour;
    use KernelTestBehaviour;

    private const PLUGIN_NAME = 'SwagTest';
    private const DEPENDENT_PLUGIN_NAME = self::PLUGIN_NAME . 'Extension';

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    private $iso = 'sv-SE';

    private $systemLanguageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();

        $this->container = $this->getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->getParameter('kernel.project_dir'),
            $this->container->get(PluginFinder::class)
        );
        $this->pluginCollection = $this->container->get(KernelPluginCollection::class);
        $this->connection = $this->container->get(Connection::class);
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();

        require_once __DIR__ . '/_fixture/plugins/SwagTest/src/Migration/Migration1536761533Test.php';

        $this->addTestPluginToKernel(self::PLUGIN_NAME);
        $this->addTestPluginToKernel('SwagTestWithoutConfig');

        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();

        $this->resetSystemLanguage();

        if (isset($_SERVER['FAKE_MIGRATION_NAMESPACE'])) {
            unset($_SERVER['FAKE_MIGRATION_NAMESPACE']);
        }

        if (isset($_SERVER['TEST_KEEP_MIGRATIONS'])) {
            unset($_SERVER['TEST_KEEP_MIGRATIONS']);
        }
    }

    public function testInstallPlugin(): void
    {
        $this->installPluginTest($this->context);
    }

    public function testInstallPluginWithoutConfig(): void
    {
        $this->installPluginWithoutConfig($this->context);
    }

    public function testInstallPluginAlreadyInstalled(): void
    {
        $this->installPluginAlreadyInstalled($this->context);
    }

    public function testInstallPluginWithUpdate(): void
    {
        $this->installPluginWithUpdate($this->context);
    }

    public function testUninstallPlugin(): void
    {
        $this->uninstallPlugin($this->context);
    }

    public function testUninstallPluginThrowsException(): void
    {
        $this->uninstallPluginThrowsException($this->context);
    }

    public function testUpdatePlugin(): void
    {
        $this->updatePlugin($this->context);
    }

    public function testUpdatePluginThrowsIfPluginIsNotInstalled(): void
    {
        $this->updatePluginThrowsIfPluginIsNotInstalled($this->context);
    }

    public function testActivatePlugin(): void
    {
        $this->activatePlugin($this->context);
    }

    public function testActivatePluginThrowsException(): void
    {
        $this->activatePluginThrowsException($this->context);
    }

    public function testDeactivatePlugin(): void
    {
        $this->deactivatePlugin($this->context);
    }

    public function testDeactivatePluginNotInstalledThrowsException(): void
    {
        $this->deactivatePluginNotInstalledThrowsException($this->context);
    }

    public function testDeactivatePluginNotActivatedThrowsException(): void
    {
        $this->deactivatePluginNotActivatedThrowsException($this->context);
    }

    public function testDontRemoveMigrations(): void
    {
        $this->dontRemoveMigrations($this->context);
    }

    public function testRemoveMigrationsCannotRemoveShopwareMigrations(): void
    {
        $this->removeMigrationsCannotRemoveShopwareMigrations($this->context);
    }

    public function testInstallPluginWithNonStandardLanguage(): void
    {
        $this->installPluginTest($this->createNonStandardLanguageContext());
    }

    public function testInstallPluginWithoutConfigWithNonStandardLanguage(): void
    {
        $this->installPluginWithoutConfig($this->createNonStandardLanguageContext());
    }

    public function testInstallPluginAlreadyInstalledWithNonStandardLanguage(): void
    {
        $this->setNewSystemLanguage($this->iso);
        $this->installPluginAlreadyInstalled($this->context);
        $this->resetSystemLanguage();
    }

    public function testInstallPluginWithUpdateWithNonStandardLanguage(): void
    {
        $this->setNewSystemLanguage($this->iso);
        $this->installPluginWithUpdate($this->context);
        $this->resetSystemLanguage();
    }

    public function testUninstallPluginWithNonStandardLanguage(): void
    {
        $this->uninstallPlugin($this->createNonStandardLanguageContext());
    }

    public function testUninstallPluginThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->uninstallPluginThrowsException($this->createNonStandardLanguageContext());
    }

    public function testUpdatePluginWithNonStandardLanguage(): void
    {
        $this->setNewSystemLanguage($this->iso);
        $this->updatePlugin($this->context);
        $this->resetSystemLanguage();
    }

    public function testActivatePluginWithNonStandardLanguage(): void
    {
        $this->activatePlugin($this->createNonStandardLanguageContext());
    }

    public function testActivatePluginThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->activatePluginThrowsException($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginWithNonStandardLanguage(): void
    {
        $this->deactivatePlugin($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginNotInstalledThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->deactivatePluginNotInstalledThrowsException($this->createNonStandardLanguageContext());
    }

    public function testDeactivatePluginNotActivatedThrowsExceptionWithNonStandardLanguage(): void
    {
        $this->deactivatePluginNotActivatedThrowsException($this->createNonStandardLanguageContext());
    }

    public function testDontRemoveMigrationsWithNonStandardLanguage(): void
    {
        $this->dontRemoveMigrations($this->createNonStandardLanguageContext());
    }

    public function testRemoveMigrationsCannotRemoveShopwareMigrationsWithNonStandardLanguage(): void
    {
        $this->removeMigrationsCannotRemoveShopwareMigrations($this->createNonStandardLanguageContext());
    }

    public function testUpdateActivatedPluginWithException(): void
    {
        $this->updateActivatedPluginWithException($this->context);
    }

    public function testUpdateActivatedPluginWithExceptionWithNonStandardLanguage(): void
    {
        $this->updateActivatedPluginWithException($this->createNonStandardLanguageContext());
    }

    public function testUpdateActivatedPluginWithExceptionOnDeactivation(): void
    {
        $this->updateActivatedPluginWithExceptionOnDeactivation($this->context);
    }

    public function testUpdateActivatedPluginWithExceptionOnDeactivationWithNonStandardLanguage(): void
    {
        $this->setNewSystemLanguage($this->iso);
        $this->updateActivatedPluginWithExceptionOnDeactivation($this->context);
        $this->resetSystemLanguage();
    }

    public function testUpdateDeactivatedPluginWithException(): void
    {
        $this->updateDeactivatedPluginWithException($this->context);
    }

    public function testUpdateDeactivatedPluginWithExceptionWithNonStandardLanguage(): void
    {
        static::markTestSkipped('Test causes other Tests to sometimes randomly fail (see NEXT-7763)');
        $this->setNewSystemLanguage($this->iso);
        $this->updateDeactivatedPluginWithException($this->context);
        $this->resetSystemLanguage();
    }

    public function updateDeactivatedPluginWithException(Context $context): void
    {
        $installedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_OLD_VERSION, $installedAt);

        $plugin = $this->getPlugin($context);
        $context->addExtension(SwagTest::THROW_ERROR_ON_UPDATE, new ArrayStruct());

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Update throws an error');
        $this->pluginLifecycleService->updatePlugin($plugin, $context);
    }

    public function updateActivatedPluginWithException(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $this->context, SwagTest::PLUGIN_OLD_VERSION);
        $activatedPlugin = $this->installAndActivatePlugin($context);

        $context->addExtension(SwagTest::THROW_ERROR_ON_UPDATE, new ArrayStruct());

        try {
            $this->pluginLifecycleService->updatePlugin($activatedPlugin, $context);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(\BadMethodCallException::class, $exception);
            static::assertStringContainsString('Update throws an error', $exception->getMessage());
        }

        $plugin = $this->getTestPlugin($context);
        static::assertFalse($plugin->getActive());
    }

    public function updateActivatedPluginWithExceptionOnDeactivation(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_OLD_VERSION);
        $activatedPlugin = $this->installAndActivatePlugin($context);

        $context->addExtension(SwagTest::THROW_ERROR_ON_UPDATE, new ArrayStruct());
        $context->addExtension(SwagTest::THROW_ERROR_ON_DEACTIVATE, new ArrayStruct());

        try {
            $this->pluginLifecycleService->updatePlugin($activatedPlugin, $context);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(\BadMethodCallException::class, $exception);
            static::assertStringContainsString('Update throws an error', $exception->getMessage());
        }

        $plugin = $this->getTestPlugin($context);
        static::assertFalse($plugin->getActive());
    }

    public function testDeactivatePluginWithDependencies(): void
    {
        $this->addTestPluginToKernel(self::DEPENDENT_PLUGIN_NAME);
        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $basePlugin = $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
        $this->pluginLifecycleService->installPlugin($basePlugin, $this->context);
        $this->pluginLifecycleService->activatePlugin($basePlugin, $this->context);

        $dependentPlugin = $this->pluginService->getPluginByName(self::DEPENDENT_PLUGIN_NAME, $this->context);
        $this->pluginLifecycleService->installPlugin($dependentPlugin, $this->context);
        $this->pluginLifecycleService->activatePlugin($dependentPlugin, $this->context);

        $this->expectException(PluginHasActiveDependantsException::class);

        try {
            $this->pluginLifecycleService->deactivatePlugin($basePlugin, $this->context);
        } catch (PluginHasActiveDependantsException $exception) {
            $params = $exception->getParameters();

            static::assertArrayHasKey('dependency', $params);
            static::assertArrayHasKey('dependants', $params);
            static::assertArrayHasKey('dependantNames', $params);

            $dependencyName = $params['dependency'];
            $dependants = $params['dependants'];
            $dependantNames = $params['dependantNames'];

            static::assertEquals(self::PLUGIN_NAME, $dependencyName);
            static::assertCount(1, $dependants);
            static::assertEquals(sprintf('"%s"', self::DEPENDENT_PLUGIN_NAME), $dependantNames);

            /* @var PluginEntity $dependant */
            $dependant = array_pop($dependants);

            static::assertInstanceOf(PluginEntity::class, $dependant);
            static::assertEquals(self::DEPENDENT_PLUGIN_NAME, $dependant->getName());

            throw $exception;
        }
    }

    private function installPluginTest(Context $context): void
    {
        $pluginInstalled = $this->installPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());

        static::assertSame(1, $this->getMigrationTestKeyCount());

        static::assertSame(7, $this->systemConfigService->get('SwagTest.config.intField'));
        static::assertNull($this->systemConfigService->get('SwagTest.config.textFieldWithoutDefault'));
        static::assertSame('string', $this->systemConfigService->get('SwagTest.config.textField'));
        static::assertNull($this->systemConfigService->get('SwagTest.config.textFieldNull'));
        static::assertFalse($this->systemConfigService->get('SwagTest.config.switchField'));
        static::assertSame(0.349831239840912348, $this->systemConfigService->get('SwagTest.config.floatField'));
    }

    private function installPluginWithoutConfig(Context $context): void
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        $plugin = $this->pluginService->getPluginByName('SwagTestWithoutConfig', $context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        $pluginInstalled = $this->pluginService->getPluginByName('SwagTestWithoutConfig', $context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
    }

    private function installPluginAlreadyInstalled(Context $context): void
    {
        $installedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_VERSION, $installedAt);

        $plugin = $this->getTestPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        $pluginInstalled = $this->getTestPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertSame(
            $installedAt,
            $pluginInstalled->getInstalledAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    private function installPluginWithUpdate(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_OLD_VERSION);
        $pluginInstalled = $this->installPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());
        static::assertNull($pluginInstalled->getUpgradedAt());
        static::assertSame(SwagTest::PLUGIN_VERSION, $pluginInstalled->getVersion());
    }

    private function uninstallPlugin(Context $context): void
    {
        $pluginInstalled = $this->installPlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->uninstallPlugin($pluginInstalled, $context);

        $pluginUninstalled = $this->getTestPlugin($context);

        static::assertNull($pluginUninstalled->getInstalledAt());
        static::assertFalse($pluginUninstalled->getActive());
    }

    private function uninstallPluginThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->uninstallPlugin($plugin, $context);
    }

    private function updatePlugin(Context $context): void
    {
        $installedAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_OLD_VERSION, $installedAt);
        static::assertSame(0, $this->getMigrationTestKeyCount());

        $plugin = $this->getPlugin($context);

        $this->pluginLifecycleService->updatePlugin($plugin, $context);

        $pluginUpdated = $this->getTestPlugin($context);

        static::assertNotNull($pluginUpdated->getUpgradedAt());
        static::assertSame(SwagTest::PLUGIN_VERSION, $pluginUpdated->getVersion());

        static::assertSame(1, $this->getMigrationTestKeyCount());
    }

    private function updatePluginThrowsIfPluginIsNotInstalled(Context $context): void
    {
        $this->createPlugin($this->pluginRepo, $context, SwagTest::PLUGIN_OLD_VERSION);
        static::assertSame(0, $this->getMigrationTestKeyCount());

        $plugin = $this->getPlugin($context);

        static::expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->updatePlugin($plugin, $context);
    }

    private function activatePlugin(Context $context): void
    {
        $this->installAndActivatePlugin($context);

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    private function activatePluginThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->activatePlugin($plugin, $context);
    }

    private function deactivatePlugin(Context $context): void
    {
        $pluginActivated = $this->installAndActivatePlugin($context);

        $this->pluginLifecycleService->deactivatePlugin($pluginActivated, $context);

        $pluginDeactivated = $this->getTestPlugin($context);

        static::assertFalse($pluginDeactivated->getActive());

        $filesystem = $this->container->get(Filesystem::class);
        $filesystem->remove(__DIR__ . '/public');
    }

    private function deactivatePluginNotInstalledThrowsException(Context $context): void
    {
        $plugin = $this->getPlugin($context);

        $this->expectException(PluginNotInstalledException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not installed.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
    }

    private function deactivatePluginNotActivatedThrowsException(Context $context): void
    {
        $pluginInstalled = $this->installPlugin($context);

        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->expectException(PluginNotActivatedException::class);
        $this->expectExceptionMessage(sprintf('Plugin "%s" is not activated.', self::PLUGIN_NAME));
        $this->pluginLifecycleService->deactivatePlugin($pluginInstalled, $context);
    }

    private function dontRemoveMigrations(Context $context): void
    {
        $_SERVER['TEST_KEEP_MIGRATIONS'] = true;

        $overAllCount = $this->getMigrationCount('');
        $swagTestCount = $this->prepareRemoveMigrationTest($context);
        static::assertSame(1, $swagTestCount);

        $newOverAllCount = $this->getMigrationCount('');
        static::assertSame($overAllCount + $swagTestCount, $newOverAllCount);
    }

    private function removeMigrationsCannotRemoveShopwareMigrations(Context $context): void
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        $overAllCount = $this->getMigrationCount('');

        $swagTest = new SwagTest(true, '', '');

        $_SERVER['FAKE_MIGRATION_NAMESPACE'] = 'Shopware\\Core';

        $exception = null;

        try {
            $swagTest->removeMigrations();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $newOverAllCount = $this->getMigrationCount('');

        static::assertSame($overAllCount, $newOverAllCount);

        static::assertNotNull($exception, 'Expected exception to be thrown');
    }

    private function addLanguage(String $iso, $id = 0): string
    {
        if ($id === 0) {
            $id = Uuid::randomHex();
        }
        $languageRepository = $this->getContainer()->get('language.repository');
        $localeId = $this->getIsoId($iso);
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => $iso,
                    'localeId' => $localeId,
                    'translationCode' => [
                        'id' => $localeId,
                        'code' => $iso,
                        'name' => 'test name',
                        'territory' => 'test',
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function setNewSystemLanguage(string $iso): void
    {
        $languageRepository = $this->getContainer()->get('language.repository');

        $localeId = $this->getIsoId($iso);
        $languageRepository->update(
            [
                ['id' => $this->systemLanguageId, 'name' => $iso, 'localeId' => $localeId,
                    'translationCode' => [
                        'id' => $localeId,
                        'code' => $iso,
                    ],
                ],
            ],
            $this->context
        );
    }

    private function resetSystemLanguage(): void
    {
        $this->setNewSystemLanguage('en-GB');
    }

    private function getIsoId(String $iso)
    {
        $result = $this->connection->executeQuery('SELECT LOWER(HEX(id)) FROM locale WHERE code = ?', [$iso]);

        return $result->fetchColumn();
    }

    private function getMigrationCount(string $namespacePrefix): int
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM migration WHERE class LIKE :class',
            ['class' => addcslashes($namespacePrefix, '\\_%') . '%']
        )
            ->fetchColumn();

        return (int) $result;
    }

    private function createNonStandardLanguageContext(): Context
    {
        $id = $this->addLanguage($this->iso);

        return new Context(new SystemSource(), [], Defaults::CURRENCY, [$id]);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->pluginCollection,
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->systemConfigService
        );
    }

    private function getMigrationTestKeyCount(): int
    {
        $result = $this->connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1536761533Test::TEST_SYSTEM_CONFIG_KEY]
        );

        return (int) $result->fetchColumn();
    }

    private function installPlugin(Context $context): PluginEntity
    {
        $plugin = $this->getPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return $this->getTestPlugin($context);
    }

    private function installAndActivatePlugin(Context $context): PluginEntity
    {
        $pluginInstalled = $this->installPlugin($context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        $this->pluginLifecycleService->activatePlugin($pluginInstalled, $context);
        $pluginActivated = $this->getTestPlugin($context);
        static::assertTrue($pluginActivated->getActive());

        return $pluginActivated;
    }

    private function getPlugin(Context $context): PluginEntity
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return $this->getTestPlugin($context);
    }

    private function getTestPlugin(Context $context): PluginEntity
    {
        return $this->pluginService->getPluginByName(self::PLUGIN_NAME, $context);
    }

    private function prepareRemoveMigrationTest(Context $context): int
    {
        $plugin = $this->getPlugin($context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        $swagTestCount = $this->getMigrationCount('SwagTest\\Migration\\');
        static::assertSame(1, $swagTestCount);

        $this->pluginLifecycleService->uninstallPlugin($plugin, $context);

        return $this->getMigrationCount('SwagTest\\Migration\\');
    }
}
