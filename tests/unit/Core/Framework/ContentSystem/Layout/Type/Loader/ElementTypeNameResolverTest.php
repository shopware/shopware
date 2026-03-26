<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;

/**
 * @internal
 */
#[CoversClass(ElementTypeNameResolver::class)]
class ElementTypeNameResolverTest extends TestCase
{
    private ElementTypeNameResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ElementTypeNameResolver();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function resolvesFilePathToElementTypeNameProvider(): iterable
    {
        yield 'simple file' => ['button.yaml', 'Sw', 'Sw:Button'];
        yield 'yml extension' => ['quick-view.yml', 'AcmeProductExtras', 'AcmeProductExtras:QuickView'];
        yield 'nested path' => ['product/listing.yaml', 'Sw', 'Sw:Product:Listing'];
        yield 'deep nesting' => ['filter/type/boolean-filter.yaml', 'Sw', 'Sw:Filter:Type:BooleanFilter'];
        yield 'single char segment' => ['a/b.yaml', 'Prefix', 'Prefix:A:B'];
        yield 'multi-hyphen' => ['my-long-name.yaml', 'X', 'X:MyLongName'];
        yield 'numeric segments' => ['v2/widget.yaml', 'Sw', 'Sw:V2:Widget'];
    }

    #[DataProvider('resolvesFilePathToElementTypeNameProvider')]
    #[TestDox('resolves "$relativePath" from prefix "$prefix" to type name "$expected"')]
    public function testResolvesFilePathToElementTypeName(string $relativePath, string $prefix, string $expected): void
    {
        static::assertSame($expected, $this->resolver->resolve($relativePath, $prefix));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function throwsForInvalidFilenameSegmentProvider(): iterable
    {
        yield 'underscore in filename segment' => ['my_button.yaml', 'my_button'];
        yield 'uppercase letter in filename segment' => ['MyButton.yaml', 'MyButton'];
        yield 'leading hyphen in segment' => ['-button.yaml', '-button'];
        yield 'trailing hyphen in segment' => ['button-.yaml', 'button-'];
        yield 'uppercase letter in directory segment' => ['My_Dir/button.yaml', 'My_Dir'];
        yield 'empty string' => ['', ''];
        yield 'non-yaml extension treated as segment' => ['button.json', 'button.json'];
    }

    #[DataProvider('throwsForInvalidFilenameSegmentProvider')]
    #[TestDox('throws for invalid filename segment "$expectedSegment" in "$relativePath"')]
    public function testThrowsForInvalidFilenameSegment(string $relativePath, string $expectedSegment): void
    {
        $this->expectExceptionObject(
            ContentSystemException::elementTypeInvalidFilename($expectedSegment, $relativePath)
        );
        $this->resolver->resolve($relativePath, 'Sw');
    }
}
