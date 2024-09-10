<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\CmsAware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareFields;

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
        static::assertEquals('cascade', $swCategories['onDelete']);

        $swMedia = $actualCmsAwareFields['sw_og_image']->toArray(self::TEST_LOCALE);
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
    }
}
