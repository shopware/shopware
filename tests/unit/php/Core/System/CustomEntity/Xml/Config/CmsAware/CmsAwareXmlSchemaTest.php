<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\CmsAware;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\XmlElements\CmsAware;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\XmlElements\Entity;

/**
 * @package content
 *
 * @internal
 * @covers \Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareXmlSchema
 */
class CmsAwareXmlSchemaTest extends TestCase
{
    private const TEST_LOCALE = 'en-GB';

    private const EXPECTED_CMS_AWARE_ENTITY_NAMES = [
        'cmsAwareOnly' => 'custom_entity_test_entity_cms_aware',
        'allFlags' => 'custom_entity_test_entity_cms_aware_admin_ui',
    ];

    public function testPublicConstants(): void
    {
        static::assertStringEndsWith(
            'src/Core/System/CustomEntity/Xml/Config/CmsAware/cms-aware-1.0.xsd',
            CmsAwareXmlSchema::XSD_FILEPATH
        );
        static::assertEquals('cms-aware.xml', CmsAwareXmlSchema::FILENAME);
    }

    public function testConstructor(): void
    {
        $cmsAware = new CmsAware();
        $cmsAwareXmlSchema = new CmsAwareXmlSchema($cmsAware);
        static::assertEquals($cmsAware, $cmsAwareXmlSchema->getCmsAware());
    }

    public function testCreateFromXmlFile(): void
    {
        $cmsAwareXmlSchema = CmsAwareXmlSchema::createFromXmlFile(__DIR__ . '/../../../_fixtures/cms-aware.xml');
        $cmsAware = $cmsAwareXmlSchema->getCmsAware();
        static::assertInstanceOf(CmsAware::class, $cmsAware);

        $entities = $cmsAware->getEntities();
        static::assertIsArray($entities);
        static::assertCount(2, $entities);

        foreach (self::EXPECTED_CMS_AWARE_ENTITY_NAMES as $expectedEntityName) {
            static::assertInstanceOf(Entity::class, $entities[$expectedEntityName]);
            static::assertEquals($expectedEntityName, $entities[$expectedEntityName]->getName());
        }
    }

    public function testGetCmsAwareFields(): void
    {
        $actualCmsAwareFields = array_reduce(CmsAwareXmlSchema::getCmsAwareFields(), static function ($accumulator, $field) {
            $accumulator[$field->getName()] = $field;

            return $accumulator;
        }, []);

        static::assertCount(9, $actualCmsAwareFields);

        foreach ($actualCmsAwareFields as $actualCmsAwareField) {
            $currentField = $actualCmsAwareField->toArray(self::TEST_LOCALE);
            static::assertStringStartsWith('sw_', $currentField['name']);
            static::assertTrue($currentField['storeApiAware']);
        }

        $swTitle = $actualCmsAwareFields['sw_title']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swTitle['type']);
        static::assertTrue($swTitle['translatable']);
        static::assertFalse($swTitle['required']);

        $swDescription = $actualCmsAwareFields['sw_content']->toArray(self::TEST_LOCALE);
        static::assertEquals('text', $swDescription['type']);
        static::assertTrue($swDescription['translatable']);
        static::assertFalse($swDescription['required']);
        static::assertFalse($swDescription['allowHtml']);

        $swCmsPage = $actualCmsAwareFields['sw_cms_page']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-one', $swCmsPage['type']);
        static::assertFalse($swCmsPage['required']);
        static::assertEquals('cms_page', $swCmsPage['reference']);
        static::assertFalse($swCmsPage['inherited']);
        static::assertEquals('set-null', $swCmsPage['onDelete']);

        $swCategories = $actualCmsAwareFields['sw_slot_config']->toArray(self::TEST_LOCALE);
        static::assertEquals('json', $swCategories['type']);
        static::assertFalse($swCategories['required']);

        $swCategories = $actualCmsAwareFields['sw_categories']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-many', $swCategories['type']);
        static::assertFalse($swCategories['required']);
        static::assertEquals('category', $swCategories['reference']);
        static::assertFalse($swCategories['inherited']);
        static::assertEquals('set-null', $swCategories['onDelete']);

        $swMedia = $actualCmsAwareFields['sw_media']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-one', $swMedia['type']);
        static::assertFalse($swMedia['required']);
        static::assertEquals('media', $swMedia['reference']);
        static::assertFalse($swMedia['inherited']);
        static::assertEquals('set-null', $swMedia['onDelete']);

        $swSeoMetaTitle = $actualCmsAwareFields['sw_seo_meta_title']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swSeoMetaTitle['type']);
        static::assertTrue($swSeoMetaTitle['translatable']);
        static::assertFalse($swSeoMetaTitle['required']);

        $swSeoMetaDescription = $actualCmsAwareFields['sw_seo_meta_description']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swSeoMetaDescription['type']);
        static::assertTrue($swSeoMetaDescription['translatable']);
        static::assertFalse($swSeoMetaDescription['required']);

        $swSeoKeywords = $actualCmsAwareFields['sw_seo_keywords']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swSeoKeywords['type']);
        static::assertTrue($swSeoKeywords['translatable']);
        static::assertFalse($swSeoKeywords['required']);
    }
}
