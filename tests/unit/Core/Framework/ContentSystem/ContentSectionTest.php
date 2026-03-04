<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSection;

/**
 * @internal
 */
#[CoversClass(ContentSection::class)]
class ContentSectionTest extends TestCase
{
    #[DataProvider('returnsRoutePathSegmentProvider')]
    #[TestDox('returns correct route path segment for each section')]
    public function testRoutePathSegmentReturnsCorrectValueForEachSection(ContentSection $section, string $expected): void
    {
        static::assertSame($expected, $section->routePathSegment());
    }

    #[DataProvider('prefixesLayoutIdProvider')]
    #[TestDox('prefixes layout id with section-specific cache tag constant')]
    public function testBuildLayoutTagPrefixesLayoutIdWithSectionConstant(ContentSection $section, string $layoutId, string $expected): void
    {
        static::assertSame($expected, $section->buildLayoutTag($layoutId));
    }

    /**
     * @param list<string> $expectedTags
     */
    #[DataProvider('returnsRouteCacheTagsProvider')]
    #[TestDox('returns correct cache tags for each section')]
    public function testBuildRouteCacheTagsIncludesMainTagAndSectionSpecificTag(ContentSection $section, array $expectedTags): void
    {
        static::assertSame($expectedTags, $section->buildRouteCacheTags('layout-42'));
    }

    /**
     * @return \Generator<string, array{ContentSection, string}>
     */
    public static function returnsRoutePathSegmentProvider(): \Generator
    {
        yield 'MAIN section returns content segment' => [ContentSection::MAIN, 'content'];
        yield 'HEADER section returns content-header segment' => [ContentSection::HEADER, 'content-header'];
        yield 'FOOTER section returns content-footer segment' => [ContentSection::FOOTER, 'content-footer'];
    }

    /**
     * @return \Generator<string, array{ContentSection, string, string}>
     */
    public static function prefixesLayoutIdProvider(): \Generator
    {
        yield 'MAIN section prefixes with content-layout-' => [ContentSection::MAIN, 'abc123', 'content-layout-abc123'];
        yield 'HEADER section prefixes with header-content-layout-' => [ContentSection::HEADER, 'abc123', 'header-content-layout-abc123'];
        yield 'FOOTER section prefixes with footer-content-layout-' => [ContentSection::FOOTER, 'abc123', 'footer-content-layout-abc123'];
    }

    /**
     * @return \Generator<string, array{ContentSection, list<string>}>
     */
    public static function returnsRouteCacheTagsProvider(): \Generator
    {
        yield 'MAIN returns exactly one deduplicated tag' => [
            ContentSection::MAIN, ['content-layout-layout-42'],
        ];
        yield 'HEADER returns main tag and header-specific tag' => [
            ContentSection::HEADER, ['content-layout-layout-42', 'header-content-layout-layout-42'],
        ];
        yield 'FOOTER returns main tag and footer-specific tag' => [
            ContentSection::FOOTER, ['content-layout-layout-42', 'footer-content-layout-layout-42'],
        ];
    }
}
