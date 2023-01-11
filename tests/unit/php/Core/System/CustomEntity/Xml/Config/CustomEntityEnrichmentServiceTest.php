<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Column;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tab;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Entities;
use Shopware\Core\System\CustomEntity\Xml\Entity;

/**
 * @package content
 *
 * @internal
 * @covers \Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService
 */
class CustomEntityEnrichmentServiceTest extends TestCase
{
    private const TEST_LOCALE = 'en-GB';

    private const FIXTURE_PATH = '%s/../../_fixtures/%s';

    private const EXPECTED_CMS_AWARE_ENTITY_NAMES = [
        'cmsAwareOnly' => 'custom_entity_test_entity_cms_aware',
        'allFlags' => 'custom_entity_test_entity_cms_aware_admin_ui',
    ];

    private const EXPECTED_ADMIN_UI_ENTITY_NAMES = [
        'adminUiOnly' => 'custom_entity_test_entity_admin_ui',
        'allFlags' => 'custom_entity_test_entity_cms_aware_admin_ui',
    ];

    private CustomEntityEnrichmentService $customEntityEnrichmentService;

    private CustomEntityXmlSchema $entitySchema;

    private AdminUiXmlSchema $adminUiXmlSchema;

    /**
     * @var Entity[]
     */
    private array $customEntities;

    public function setUp(): void
    {
        parent::setUp();

        $this->customEntityEnrichmentService = new CustomEntityEnrichmentService(
            new AdminUiXmlSchemaValidator()
        );
        $this->entitySchema = $this->getCustomEntities();
        $this->adminUiXmlSchema = $this->getAdminUiXmlSchema();

        /** @var Entities $outerEntities */
        $outerEntities = $this->entitySchema->getEntities();
        $this->customEntities = $outerEntities->getEntities();
    }

    public function testEnrichCmsAwareAffectedEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            null
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            if (!\in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true)) {
                continue;
            }

            $fieldNames = array_map(static function ($field) {
                return $field->getName();
            }, $enrichedCustomEntity->getFields());

            static::assertCount(13, $fieldNames);

            static::assertContains('sw_title', $fieldNames);
            static::assertContains('sw_content', $fieldNames);
            static::assertContains('sw_cms_page', $fieldNames);
            static::assertContains('sw_slot_config', $fieldNames);
            static::assertContains('sw_categories', $fieldNames);
            static::assertContains('sw_media', $fieldNames);
            static::assertContains('sw_seo_meta_title', $fieldNames);
            static::assertContains('sw_seo_meta_description', $fieldNames);
            static::assertContains('sw_seo_keywords', $fieldNames);

            static::assertCount(1, $enrichedCustomEntity->getFlags());
            static::assertNotNull($enrichedCustomEntity->getFlags()['cms-aware']);
            static::assertContains(
                $enrichedCustomEntity->getFlags()['cms-aware']['name'],
                self::EXPECTED_CMS_AWARE_ENTITY_NAMES
            );
        }
    }

    public function testEnrichCmsAwareUnaffectedEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            null
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            if (\in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true)) {
                continue;
            }

            $fieldNames = array_map(static function ($field) {
                return $field->getName();
            }, $enrichedCustomEntity->getFields());

            static::assertCount(4, $fieldNames);

            static::assertNotContains('sw_title', $fieldNames);
            static::assertNotContains('sw_description', $fieldNames);
            static::assertNotContains('sw_cms_page', $fieldNames);
            static::assertNotContains('sw_categories', $fieldNames);
            static::assertNotContains('sw_media', $fieldNames);
            static::assertNotContains('sw_seo_meta_title', $fieldNames);
            static::assertNotContains('sw_seo_meta_description', $fieldNames);
            static::assertNotContains('sw_seo_keywords', $fieldNames);

            static::assertCount(0, $enrichedCustomEntity->getFlags());
        }
    }

    public function testEnrichAdminUiAffectedEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            if (!\in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true)) {
                continue;
            }
            if (\array_key_exists('cms-aware', $enrichedCustomEntity->getFlags())) {
                static::assertCount(13, $enrichedCustomEntity->getFields());
            } else {
                static::assertCount(4, $enrichedCustomEntity->getFields());
            }

            static::assertNotNull($enrichedCustomEntity->getFlags()['admin-ui']);

            /** @var AdminUiEntity $adminUi */
            $adminUi = $enrichedCustomEntity->getFlags()['admin-ui'];
            static::assertEquals('sw-content', $adminUi->getVars()['navigationParent']);
            static::assertEquals(50, $adminUi->getVars()['position']);
            static::assertEquals('regular-tools-alt', $adminUi->getVars()['icon']);
            static::assertEquals('#f00', $adminUi->getVars()['color']);

            /** @var list<Column> $listingColumns */
            $listingColumns = $adminUi->getListing()->getColumns()->toArray(self::TEST_LOCALE);
            static::assertCount(3, $listingColumns);

            $listingColumnNames = array_map(static function ($column) {
                return $column->getVars()['ref'];
            }, $listingColumns);

            static::assertIsArray($listingColumns);

            // ToDo NEXT-24184 - Remove PHPStan ignore
            static::assertContains('test_string', $listingColumnNames);
            static::assertObjectNotHasAttribute('hidden', $listingColumns[0]);
            static::assertContains('test_text', $listingColumnNames);
            static::assertObjectNotHasAttribute('hidden', $listingColumns[1]);
            static::assertContains('test_int', $listingColumnNames);
            static::assertObjectHasAttribute('hidden', $listingColumns[2]);
            static::assertTrue($listingColumns[2]->getVars()['hidden']);

            /** @var list<Tab> $detailTabs */
            $detailTabs = $adminUi->getDetail()->getTabs()->toArray(self::TEST_LOCALE);
            static::assertCount(2, $detailTabs);
            static::assertCount(2, $detailTabs[0]->getCards());
            static::assertEquals('firstTab', $detailTabs[0]->getName());
            static::assertCount(2, $detailTabs[1]->getCards());
            static::assertEquals('secondTab', $detailTabs[1]->getName());

            $exampleCards = $detailTabs[1]->getCards();
            static::assertCount(2, $exampleCards);
            static::assertEquals('testWithAll', $exampleCards[0]->getName());

            $exampleCardFields = $exampleCards[0]->getFields();
            static::assertCount(4, $exampleCardFields);
            static::assertEquals('test_string', $exampleCardFields[0]->getRef());
            static::assertEquals('test_text', $exampleCardFields[1]->getRef());
            static::assertEquals('test_int', $exampleCardFields[2]->getRef());
            static::assertEquals('test_float', $exampleCardFields[3]->getRef());
        }
    }

    public function testEnrichAdminUiUnaffectedEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            if (\in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true)) {
                continue;
            }
            if (\array_key_exists('cms-aware', $enrichedCustomEntity->getFlags())) {
                static::assertCount(13, $enrichedCustomEntity->getFields());
            } else {
                static::assertCount(4, $enrichedCustomEntity->getFields());
            }

            static::assertArrayNotHasKey('admin-ui', $enrichedCustomEntity->getFlags());
        }
    }

    public function testFullEnrichCustomEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            $hasCmsAware = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true);
            $hasAdminUi = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true);

            if (!($hasCmsAware && $hasAdminUi)) {
                continue;
            }

            static::assertCount(13, $enrichedCustomEntity->getFields());
            static::assertCount(2, $enrichedCustomEntity->getFlags());
            static::assertNotNull($enrichedCustomEntity->getFlags()['cms-aware']);
            static::assertNotNull($enrichedCustomEntity->getFlags()['admin-ui']);
            static::assertEquals(
                self::EXPECTED_CMS_AWARE_ENTITY_NAMES['allFlags'],
                $enrichedCustomEntity->getFlags()['cms-aware']['name'],
            );
            static::assertEquals(
                self::EXPECTED_ADMIN_UI_ENTITY_NAMES['allFlags'],
                $enrichedCustomEntity->getFlags()['admin-ui']->getName(),
            );

            /** @var AdminUiEntity $adminUi */
            $adminUi = $enrichedCustomEntity->getFlags()['admin-ui'];
            static::assertEquals('sw-content', $adminUi->getVars()['navigationParent']);
            static::assertEquals(50, $adminUi->getVars()['position']);

            $listingColumns = $adminUi->getListing()->getColumns()->toArray(self::TEST_LOCALE);
            static::assertCount(3, $listingColumns);

            $detailTabs = $adminUi->getDetail()->getTabs();
            static::assertCount(2, $detailTabs->toArray(self::TEST_LOCALE));

            /** @phpstan-ignore-next-line */
            $exampleCards = $detailTabs->toArray(self::TEST_LOCALE)[0]->getCards();
            static::assertCount(2, $exampleCards);

            $exampleCardFields = $exampleCards[0]->getFields();
            static::assertCount(1, $exampleCardFields);
        }
    }

    public function testFullEnrichAffectedCmsAwareEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            $hasCmsAware = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true);
            $hasAdminUi = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true);

            if (!($hasCmsAware && !$hasAdminUi)) {
                continue;
            }

            static::assertCount(13, $enrichedCustomEntity->getFields());
            static::assertCount(1, $enrichedCustomEntity->getFlags());
            static::assertNotNull($enrichedCustomEntity->getFlags()['cms-aware']);
            static::assertArrayNotHasKey('admin-ui', $enrichedCustomEntity->getFlags());
            static::assertEquals(
                self::EXPECTED_CMS_AWARE_ENTITY_NAMES['cmsAwareOnly'],
                $enrichedCustomEntity->getFlags()['cms-aware']['name'],
            );
        }
    }

    public function testFullEnrichAffectedAdminUiEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            $hasCmsAware = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true);
            $hasAdminUi = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true);

            if (!(!$hasCmsAware && $hasAdminUi)) {
                continue;
            }

            static::assertCount(4, $enrichedCustomEntity->getFields());
            static::assertCount(1, $enrichedCustomEntity->getFlags());
            static::assertArrayNotHasKey('cms-aware', $enrichedCustomEntity->getFlags());
            static::assertNotNull($enrichedCustomEntity->getFlags()['admin-ui']);
            static::assertEquals(
                self::EXPECTED_ADMIN_UI_ENTITY_NAMES['adminUiOnly'],
                $enrichedCustomEntity->getFlags()['admin-ui']->getName(),
            );

            /** @var AdminUiEntity $adminUi */
            $adminUi = $enrichedCustomEntity->getFlags()['admin-ui'];
            static::assertEquals('sw-content', $adminUi->getVars()['navigationParent']);
            static::assertEquals(50, $adminUi->getVars()['position']);

            $listingColumns = $adminUi->getListing()->getColumns()->toArray(self::TEST_LOCALE);
            static::assertCount(3, $listingColumns);

            $detailTabs = $adminUi->getDetail()->getTabs();
            static::assertCount(2, $detailTabs->toArray(self::TEST_LOCALE));

            /** @phpstan-ignore-next-line */
            $exampleCards = $detailTabs->toArray(self::TEST_LOCALE)[0]->getCards();
            static::assertCount(2, $exampleCards);

            $exampleCardFields = $exampleCards[0]->getFields();
            static::assertCount(1, $exampleCardFields);
        }
    }

    public function testFullEnrichUnaffectedEntities(): void
    {
        static::assertCount(4, $this->customEntities);

        foreach ($this->customEntities as $entity) {
            static::assertCount(4, $entity->getFields());
            static::assertCount(0, $entity->getFlags());
        }

        $enrichedEntities = $this->customEntityEnrichmentService->enrich(
            $this->entitySchema,
            $this->adminUiXmlSchema
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $enrichedEntities);

        /** @var Entities $outerEnrichedCustomEntities */
        $outerEnrichedCustomEntities = $enrichedEntities->getEntities();
        $enrichedCustomEntities = $outerEnrichedCustomEntities->getEntities();

        foreach ($enrichedCustomEntities as $enrichedCustomEntity) {
            $hasCmsAware = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_CMS_AWARE_ENTITY_NAMES, true);
            $hasAdminUi = \in_array($enrichedCustomEntity->getName(), self::EXPECTED_ADMIN_UI_ENTITY_NAMES, true);

            if ($hasCmsAware || $hasAdminUi) {
                continue;
            }

            static::assertCount(4, $enrichedCustomEntity->getFields());
            static::assertCount(0, $enrichedCustomEntity->getFlags());
            static::assertArrayNotHasKey('cms-aware', $enrichedCustomEntity->getFlags());
            static::assertArrayNotHasKey('admin-ui', $enrichedCustomEntity->getFlags());
        }
    }

    private function getCustomEntities(): CustomEntityXmlSchema
    {
        $configPath = sprintf(
            self::FIXTURE_PATH,
            __DIR__,
            CustomEntityXmlSchema::FILENAME
        );

        return CustomEntityXmlSchema::createFromXmlFile($configPath);
    }

    private function getAdminUiXmlSchema(): AdminUiXmlSchema
    {
        $configPath = sprintf(
            self::FIXTURE_PATH,
            __DIR__,
            AdminUiXmlSchema::FILENAME
        );

        return AdminUiXmlSchema::createFromXmlFile($configPath);
    }
}
