<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeTranslationCollection::class)]
class ThemeTranslationCollectionTest extends TestCase
{
    public function testGetThemeIds(): void
    {
        $collection = new ThemeTranslationCollection([
            self::translation('theme-a', 'language-a'),
            self::translation('theme-b', 'language-b'),
        ]);

        static::assertSame(
            ['theme-a-language-a' => 'theme-a', 'theme-b-language-b' => 'theme-b'],
            $collection->getThemeIds()
        );
    }

    public function testFilterByThemeId(): void
    {
        $match = self::translation('theme-a', 'language-a');

        $collection = new ThemeTranslationCollection([$match, self::translation('theme-b', 'language-b')]);
        $filtered = $collection->filterByThemeId('theme-a');

        static::assertSame([$match], array_values($filtered->getElements()));
    }

    public function testGetLanguageIds(): void
    {
        $collection = new ThemeTranslationCollection([
            self::translation('theme-a', 'language-a'),
            self::translation('theme-b', 'language-b'),
        ]);

        static::assertSame(
            ['theme-a-language-a' => 'language-a', 'theme-b-language-b' => 'language-b'],
            $collection->getLanguageIds()
        );
    }

    public function testFilterByLanguageId(): void
    {
        $match = self::translation('theme-a', 'language-a');

        $collection = new ThemeTranslationCollection([$match, self::translation('theme-b', 'language-b')]);
        $filtered = $collection->filterByLanguageId('language-a');

        static::assertSame([$match], array_values($filtered->getElements()));
    }

    private static function translation(string $themeId, string $languageId): ThemeTranslationEntity
    {
        $translation = new ThemeTranslationEntity();
        $translation->setUniqueIdentifier($themeId . '-' . $languageId);
        $translation->setThemeId($themeId);
        $translation->setLanguageId($languageId);

        return $translation;
    }
}
