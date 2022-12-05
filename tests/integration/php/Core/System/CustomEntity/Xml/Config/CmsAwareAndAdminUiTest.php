<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomEntity\Xml\Config;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 * @group slow
 * @group skip-paratest
 */
class CmsAwareAndAdminUiTest extends TestCase
{
    use PluginTestsHelper;
    use KernelTestBehaviour;

    private EntityRepository $appRepository;

    private EntityRepository $pluginRepo;

    private PluginService $pluginService;

    private ContainerInterface $container;

    private PluginLifecycleService $pluginLifecycleService;

    private Context $context;

    private SystemConfigService $systemConfigService;

    private AppLifecycle $appLifecycle;

    private Connection $connection;

    private string $dbSchemaName;

    public function setUp(): void
    {
        $this->dbSchemaName = $this->getDbSchemaName((new TestBootstrapper())->getDatabaseUrl());
        $this->container = $this->getContainer();
        $this->connection = $this->container->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCmsAwareAndAdminUiForApp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);

        $appEntity = $this->installAndCheckApp();
        $this->assertCmsAwareAndAdminUiIsInstalled($appEntity);

        $this->uninstallApp();
        $this->assertCmsAwareAndAdminUiIsUninstalled();
    }

    public function testCmsAwareAndAdminUiForPlugin(): void
    {
        $pluginEntity = $this->installAndCheckPlugin();
        $this->assertCmsAwareAndAdminUiIsInstalled($pluginEntity);

        $this->uninstallPlugin($pluginEntity);
        $this->assertCmsAwareAndAdminUiIsUninstalled();
    }

    private function installAndCheckApp(): AppEntity
    {
        $this->appLifecycle->install(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/apps/cmsAwareAndAdminUiApp/manifest.xml'),
            true,
            $this->context
        );

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(
            new Criteria(),
            $this->context
        )->getEntities();
        static::assertCount(1, $apps);
        $appEntity = $apps->first();
        static::assertNotNull($appEntity);
        static::assertEquals('testCmsAwareAndAdminUi', $appEntity->getName());

        return $appEntity;
    }

    private function uninstallApp(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'testCmsAwareAndAdminUi'));
        $result = $this->appRepository->search($criteria, $this->context);

        /** @var AppCollection $appCollection */
        $appCollection = $result->getEntities();

        /** @var AppEntity $app */
        $app = $appCollection->first();

        $this->appLifecycle->delete($app->getName(), ['id' => $app->getId()], $this->context);
    }

    private function installAndCheckPlugin(): PluginEntity
    {
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->pluginService = $this->createPluginService(
            __DIR__ . '/_fixtures/plugins',
            $this->container->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->get(PluginFinder::class)
        );
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->pluginLifecycleService = $this->container->get(PluginLifecycleService::class);

        $this->addTestPluginToKernel(
            __DIR__ . '/_fixtures/plugins/SwagTestCmsAwareAndAdminUi',
            'SwagTestCmsAwareAndAdminUi'
        );

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $this->pluginLifecycleService->installPlugin(
            $this->pluginService->getPluginByName(
                'SwagTestCmsAwareAndAdminUi',
                $this->context
            ),
            $this->context
        );

        $pluginInstalled = $this->pluginService->getPluginByName('SwagTestCmsAwareAndAdminUi', $this->context);
        static::assertNotNull($pluginInstalled->getInstalledAt());

        return $pluginInstalled;
    }

    private function uninstallPlugin(PluginEntity $pluginEntity): void
    {
        $this->pluginLifecycleService->uninstallPlugin($pluginEntity, $this->context);

        $pluginUninstalled = $this->pluginService->getPluginByName('SwagTestCmsAwareAndAdminUi', $this->context);

        $pluginUninstalledConfigs = $this->systemConfigService->all();
        static::assertArrayNotHasKey('SwagTest', $pluginUninstalledConfigs);
        static::assertNull($pluginUninstalled->getInstalledAt());
        static::assertFalse($pluginUninstalled->getActive());
    }

    /**
     * assert if all data and data structures are created as expected for this custom entity
     */
    private function assertCmsAwareAndAdminUiIsInstalled(AppEntity|PluginEntity $entity): void
    {
        static::assertTrue(
            $this->dbHasTable('custom_entity_test'),
            'The custom entity should be created'
        );

        static::assertTrue(
            $this->dbHasTable('custom_entity_test_sw_categories'),
            'This table for the many-to-many fields of this custom entity should be created'
        );

        static::assertTrue(
            $this->dbHasTable('custom_entity_test_translation'),
            'The translation table for this custom entity should be created'
        );

        static::assertEqualsCanonicalizing(
            [
                'id',
                'sw_cms_page_id',
                'sw_cms_page_version_id',
                'sw_media_id',
                'created_at',
                'updated_at',
                'custom_entity_string_field',
                'custom_entity_int_field',
                'sw_slot_config',
            ],
            $this->getTableColumns('custom_entity_test'),
            'Exactly these columns should exist for this cms-aware custom entity '
        );

        static::assertEqualsCanonicalizing(
            [
                'custom_entity_test_id',
                'language_id',
                'created_at',
                'updated_at',
                'sw_title',
                'sw_content',
                'sw_seo_meta_title',
                'sw_seo_meta_description',
                'sw_seo_keywords',
            ],
            $this->getTableColumns('custom_entity_test_translation'),
            'The fields translation table of this custom entity should have exactly this fields'
        );

        static::assertEqualsCanonicalizing(
            [
                'custom_entity_test_id',
                'category_id',
                'category_version_id',
            ],
            $this->getTableColumns('custom_entity_test_sw_categories'),
            'A cms-aware custom entity should be connected to the categories'
        );

        $idColumn = match (\get_class($entity)) {
            AppEntity::class => 'app_id',
            PluginEntity::class => 'plugin_id',
            default => throw new \Exception('Wrong Entity!')
        };
        $cmsAwareAndAdminUiSettings = $this->connection->executeQuery(
            "SELECT flags, flag_config FROM custom_entity WHERE $idColumn = :id",
            ['id' => Uuid::fromHexToBytes($entity->getId())]
        )->fetchAssociative();

        static::assertNotFalse($cmsAwareAndAdminUiSettings);
        static::assertCount(2, $cmsAwareAndAdminUiSettings);
        static::assertEquals(
            ['cms-aware', 'admin-ui'],
            $this->jsonDecode($cmsAwareAndAdminUiSettings['flags'])
        );
        static::assertIsString($flagConfigJson = file_get_contents(__DIR__ . '/_fixtures/other/flag_config.json'));
        static::assertEquals(
            $this->jsonDecode($flagConfigJson),
            $this->jsonDecode($cmsAwareAndAdminUiSettings['flag_config'])
        );
    }

    /**
     * assert if all data and data structures are removed as expected for this custom entity
     */
    private function assertCmsAwareAndAdminUiIsUninstalled(): void
    {
        static::assertEquals(
            0,
            $this->connection->executeQuery(
                'SELECT COUNT(*) FROM custom_entity'
            )->fetchFirstColumn()[0],
            'No custom entity at all should be registered'
        );

        static::assertFalse(
            $this->dbHasTable('custom_entity_test'),
            'The custom entity should be removed'
        );

        static::assertFalse(
            $this->dbHasTable('custom_entity_test_sw_categories'),
            'This table for the many-to-many fields of this custom entity should be removed'
        );

        static::assertFalse(
            $this->dbHasTable('custom_entity_test_translation'),
            'The translation table for this custom entity should be removed'
        );
    }

    private function dbHasTable(string $tableName): bool
    {
        return ((int) ($this->connection->executeQuery(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = :tableName AND TABLE_SCHEMA = :dbSchema',
            [
                'tableName' => $tableName,
                'dbSchema' => $this->dbSchemaName,
            ]
        )->fetchFirstColumn()[0])) === 1;
    }

    /**
     * @return array<int, string|int>
     */
    private function getTableColumns(string $tableName): array
    {
        return \array_keys(
            $this->connection->executeQuery(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = :tableName AND TABLE_SCHEMA = :dbSchema',
                [
                    'tableName' => $tableName,
                    'dbSchema' => $this->dbSchemaName,
                ]
            )->fetchAllAssociativeIndexed()
        );
    }

    private function getDbSchemaName(string $dbUrl): string
    {
        return substr($dbUrl, strrpos($dbUrl, '/') + 1);
    }

    /**
     * @return array<mixed>
     */
    private function jsonDecode(string $json): array
    {
        return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
