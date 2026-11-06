<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetNameResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutPresetNameResolver::class)]
class LayoutPresetNameResolverTest extends TestCase
{
    private LayoutPresetNameResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LayoutPresetNameResolver();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function resolvesFilePathToPresetIdProvider(): iterable
    {
        yield 'simple file' => ['category-page.yaml', 'Sw', 'Sw:CategoryPage'];
        yield 'yml extension' => ['hero.yml', 'AcmeThemes', 'AcmeThemes:Hero'];
        yield 'nested path' => ['product/detail.yaml', 'Sw', 'Sw:Product:Detail'];
        yield 'deep nesting' => ['landing/hero/split.yaml', 'Sw', 'Sw:Landing:Hero:Split'];
        yield 'single char segment' => ['a/b.yaml', 'Prefix', 'Prefix:A:B'];
        yield 'multi-hyphen' => ['media-and-text.yaml', 'X', 'X:MediaAndText'];
        yield 'numeric segments' => ['v2/banner.yaml', 'Sw', 'Sw:V2:Banner'];
    }

    #[DataProvider('resolvesFilePathToPresetIdProvider')]
    #[TestDox('resolves "$relativePath" from prefix "$prefix" to preset id "$expected"')]
    public function testResolvesFilePathToPresetId(string $relativePath, string $prefix, string $expected): void
    {
        static::assertSame($expected, $this->resolver->resolve($relativePath, $prefix));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function throwsForInvalidFilenameSegmentProvider(): iterable
    {
        yield 'underscore in filename segment' => ['my_preset.yaml', 'my_preset'];
        yield 'uppercase letter in filename segment' => ['MyPreset.yaml', 'MyPreset'];
        yield 'leading hyphen in segment' => ['-preset.yaml', '-preset'];
        yield 'trailing hyphen in segment' => ['preset-.yaml', 'preset-'];
        yield 'uppercase letter in directory segment' => ['My_Dir/preset.yaml', 'My_Dir'];
        yield 'empty string' => ['', ''];
        yield 'non-yaml extension treated as segment' => ['preset.json', 'preset.json'];
    }

    #[DataProvider('throwsForInvalidFilenameSegmentProvider')]
    #[TestDox('throws for invalid filename segment "$expectedSegment" in "$relativePath"')]
    public function testThrowsForInvalidFilenameSegment(string $relativePath, string $expectedSegment): void
    {
        $this->expectExceptionObject(
            ContentSystemException::layoutPresetInvalidFilename($expectedSegment, $relativePath)
        );
        $this->resolver->resolve($relativePath, 'Sw');
    }
}
