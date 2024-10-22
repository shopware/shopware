<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\CmsAware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareFields;
use Shopware\Core\System\CustomEntity\Xml\Field\Field;
use Shopware\Core\System\CustomEntity\Xml\Field\JsonField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToManyField;
use Shopware\Core\System\CustomEntity\Xml\Field\ManyToOneField;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;
use Shopware\Core\System\CustomEntity\Xml\Field\TextField;

/**
 * @internal
 */
#[CoversClass(CmsAwareFields::class)]
class CmsAwareFieldsTest extends TestCase
{
    private const TEST_LOCALE = 'en-GB';

    public function testGetCmsAwareFields(): void
    {
        $actualCmsAwareFields = array_reduce(CmsAwareFields::getCmsAwareFields(), static function ($accumulator, $field) {
            $accumulator[$field->getName()] = $field;

            return $accumulator;
        }, []);

        static::assertCount(11, $actualCmsAwareFields);

        foreach ($actualCmsAwareFields as $actualCmsAwareField) {
            static::assertInstanceOf(Field::class, $actualCmsAwareField);
            $currentField = $actualCmsAwareField->toArray(self::TEST_LOCALE);
            static::assertStringStartsWith('sw_', $currentField['name']);
            static::assertTrue($currentField['storeApiAware']);
        }

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['sw_title']);
        $swTitle = $actualCmsAwareFields['sw_title']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swTitle['type']);
        static::assertTrue($swTitle['translatable']);
        static::assertFalse($swTitle['required']);

        static::assertInstanceOf(TextField::class, $actualCmsAwareFields['sw_content']);
        $swDescription = $actualCmsAwareFields['sw_content']->toArray(self::TEST_LOCALE);
        static::assertEquals('text', $swDescription['type']);
        static::assertTrue($swDescription['translatable']);
        static::assertFalse($swDescription['required']);
        static::assertFalse($swDescription['allowHtml']);

        static::assertInstanceOf(ManyToOneField::class, $actualCmsAwareFields['sw_cms_page']);
        $swCmsPage = $actualCmsAwareFields['sw_cms_page']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-one', $swCmsPage['type']);
        static::assertFalse($swCmsPage['required']);
        static::assertEquals('cms_page', $swCmsPage['reference']);
        static::assertFalse($swCmsPage['inherited']);
        static::assertEquals('set-null', $swCmsPage['onDelete']);

        static::assertInstanceOf(JsonField::class, $actualCmsAwareFields['sw_slot_config']);
        $swCategories = $actualCmsAwareFields['sw_slot_config']->toArray(self::TEST_LOCALE);
        static::assertEquals('json', $swCategories['type']);
        static::assertFalse($swCategories['required']);

        static::assertInstanceOf(ManyToManyField::class, $actualCmsAwareFields['sw_categories']);
        $swCategories = $actualCmsAwareFields['sw_categories']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-many', $swCategories['type']);
        static::assertFalse($swCategories['required']);
        static::assertEquals('category', $swCategories['reference']);
        static::assertFalse($swCategories['inherited']);
        static::assertEquals('cascade', $swCategories['onDelete']);

        static::assertInstanceOf(ManyToOneField::class, $actualCmsAwareFields['sw_og_image']);
        $swMedia = $actualCmsAwareFields['sw_og_image']->toArray(self::TEST_LOCALE);
        static::assertEquals('many-to-one', $swMedia['type']);
        static::assertFalse($swMedia['required']);
        static::assertEquals('media', $swMedia['reference']);
        static::assertFalse($swMedia['inherited']);
        static::assertEquals('set-null', $swMedia['onDelete']);

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['sw_seo_meta_title']);
        $swSeoMetaTitle = $actualCmsAwareFields['sw_seo_meta_title']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swSeoMetaTitle['type']);
        static::assertTrue($swSeoMetaTitle['translatable']);
        static::assertFalse($swSeoMetaTitle['required']);

        static::assertInstanceOf(StringField::class, $actualCmsAwareFields['sw_seo_meta_description']);
        $swSeoMetaDescription = $actualCmsAwareFields['sw_seo_meta_description']->toArray(self::TEST_LOCALE);
        static::assertEquals('string', $swSeoMetaDescription['type']);
        static::assertTrue($swSeoMetaDescription['translatable']);
        static::assertFalse($swSeoMetaDescription['required']);
    }
}
