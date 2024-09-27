<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomEntity\Xml\Config;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('slow')]
#[Group('skip-paratest')]
class CmsAwareAndAdminUiTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const CUSTOM_ENTITY_NAME = 'custom_entity_test';
    private const APP_NAME = 'testCmsAwareAndAdminUi';
    private const PLUGIN_NAME = 'SwagTestCmsAwareAndAdminUi';

    private EntityRepository $appRepository;

    private EntityRepository $pluginRepository;

    private EntityRepository $languageRepository;

    private PluginService $pluginService;

    private PluginLifecycleService $pluginLifecycleService;

    private PluginFinder $pluginFinder;

    private Context $context;

    private AppLifecycle $appLifecycle;

    private Connection $connection;

    protected function setUp(): void
    {
        static::markTestSkipped('cms-aware will be re-implemented via NEXT-22697');
        $this->context = Context::createDefaultContext();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->appRepository = $this->getContainer()->get('app.repository');

        $this->pluginFinder = $this->getContainer()->get(PluginFinder::class);
        $this->pluginRepository = $this->getContainer()->get('plugin.repository');
        $this->pluginLifecycleService = $this->getContainer()->get(PluginLifecycleService::class);

        $this->languageRepository = $this->getContainer()->get('language.repository');
    }

    public function testCmsAwareAndAdminUiForApp(): void
    {
        $appEntity = $this->installAndActivateApp();
        $this->assertCmsAwareAndAdminUiIsInstalled($appEntity);

        $this->uninstallApp($appEntity);
        $this->assertCmsAwareAndAdminUiIsUninstalled();
    }

    public function testCmsAwareAndAdminUiForPlugin(): void
    {
        $pluginEntity = $this->installPlugin();
        $this->assertCmsAwareAndAdminUiIsInstalled($pluginEntity);

        $this->uninstallPlugin($pluginEntity);
        $this->assertCmsAwareAndAdminUiIsUninstalled();
    }

    private function installAndActivateApp(): AppEntity
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
        static::assertEquals(self::APP_NAME, $appEntity->getName());

        return $appEntity;
    }

    private function uninstallApp(AppEntity $appEntity): void
    {
        $this->appLifecycle->delete($appEntity->getName(), ['id' => $appEntity->getId()], $this->context);
    }

    private function installPlugin(): PluginEntity
    {
        $this->pluginService = $this->createPluginService(
            __DIR__ . '/_fixtures/plugins',
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->pluginRepository,
            $this->languageRepository,
            $this->pluginFinder
        );

        $this->addTestPluginToKernel(
            __DIR__ . '/_fixtures/plugins/' . self::PLUGIN_NAME,
            self::PLUGIN_NAME
        );

        $this->pluginService->refreshPlugins($this->context, new NullIO());

        $this->pluginLifecycleService->installPlugin(
            $this->pluginService->getPluginByName(
                self::PLUGIN_NAME,
                $this->context
            ),
            $this->context
        );

        $installedPlugin = $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
        static::assertNotNull($installedPlugin->getInstalledAt());

        return $installedPlugin;
    }

    private function uninstallPlugin(PluginEntity $pluginEntity): void
    {
        $this->pluginLifecycleService->uninstallPlugin($pluginEntity, $this->context);

        $uninstalledPlugin = $this->pluginService->getPluginByName(self::PLUGIN_NAME, $this->context);
        static::assertNull($uninstalledPlugin->getInstalledAt());
        static::assertFalse($uninstalledPlugin->getActive());

        $this->connection->executeStatement(
            'DELETE FROM plugin WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($pluginEntity->getId())]
        );
    }

    /**
     * assert if all data and data structures are created as expected for this custom entity
     */
    private function assertCmsAwareAndAdminUiIsInstalled(AppEntity|PluginEntity $entity): void
    {
        static::assertTrue(
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME),
            'The custom entity should be created'
        );

        static::assertEqualsCanonicalizing(
            [
                // fields every custom entity will have
                'id',
                'created_at',
                'updated_at',

                // cms-aware specific fields
                'sw_cms_page_id',
                'sw_cms_page_version_id',
                'sw_og_image_id',
                'sw_slot_config',

                // fields specific for this custom entity
                'custom_entity_string_field',
                'custom_entity_int_field',
            ],
            $this->getTableColumns(self::CUSTOM_ENTITY_NAME),
            'Exactly these columns should exist for this cms-aware custom entity'
        );

        static::assertTrue(
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME . '_translation'),
            'The translation table for this custom entity should be created'
        );

        static::assertEqualsCanonicalizing(
            [
                // fields every custom entity will have
                'created_at',
                'updated_at',

                // field for language identification
                'language_id',

                // fields specific for this custom entity
                'custom_entity_test_id',

                // @todo NEXT-22697 - Re-implement, when re-enabling cms-aware
                // cms aware specific fields
                'sw_title',
                'sw_content',
                'sw_seo_meta_title',
                'sw_seo_meta_description',
                'sw_seo_url',
                'sw_og_title',
                'sw_og_description',
            ],
            $this->getTableColumns(self::CUSTOM_ENTITY_NAME . '_translation'),
            'The fields translation table of this custom entity should have exactly these fields'
        );

        static::assertTrue(
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME . '_sw_categories'),
            'This table for the many-to-many fields of this custom entity should be created'
        );

        static::assertEqualsCanonicalizing(
            [
                'custom_entity_test_id',
                'category_id',
                'category_version_id',
            ],
            $this->getTableColumns(self::CUSTOM_ENTITY_NAME . '_sw_categories'),
            'A cms-aware custom entity should be connected to the categories'
        );

        $idColumn = match ($entity::class) {
            AppEntity::class => 'app_id',
            PluginEntity::class => 'plugin_id',
            default => throw new \Exception('Wrong Entity!'),
        };
        $cmsAwareAndAdminUiSettings = $this->connection->executeQuery(
            "SELECT flags FROM custom_entity WHERE $idColumn = :id",
            ['id' => Uuid::fromHexToBytes($entity->getId())]
        )->fetchAssociative();

        static::assertNotFalse($cmsAwareAndAdminUiSettings);
        static::assertCount(2, $cmsAwareAndAdminUiSettings);

        static::assertIsString($flagsJson = file_get_contents(__DIR__ . '/_fixtures/other/flags.json'));
        static::assertEquals(
            $this->jsonDecode($flagsJson),
            $this->jsonDecode($cmsAwareAndAdminUiSettings['flags'])
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
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME),
            'The custom entity should be removed'
        );

        static::assertFalse(
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME . '_sw_categories'),
            'This table for the many-to-many fields of this custom entity should be removed'
        );

        static::assertFalse(
            $this->dbHasTable(self::CUSTOM_ENTITY_NAME . '_translation'),
            'The translation table for this custom entity should be removed'
        );
    }

    private function dbHasTable(string $tableName): bool
    {
        return EntityDefinitionQueryHelper::tableExists($this->connection, $tableName);
    }

    /**
     * @return array<int, string|int>
     */
    private function getTableColumns(string $tableName): array
    {
        return \array_keys(
            $this->connection->fetchAllAssociativeIndexed(
                "SHOW COLUMNS FROM $tableName"
            )
        );
    }

    /**
     * @return array<mixed>
     */
    private function jsonDecode(string $json): array
    {
        return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
