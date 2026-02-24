<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubPathStruct;

/**
 * @internal
 */
#[CoversClass(ContextPathResolver::class)]
class ContextPathResolverTest extends TestCase
{
    private ContextPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ContextPathResolver();
    }

    #[TestDox('parses key with single segment after root, returning tail segments')]
    public function testParseContextKeyWithSingleDottedKey(): void
    {
        $result = $this->resolver->parseContextKey('product.cover');

        static::assertSame(['cover'], $result);
    }

    #[TestDox('parses key with no dot, returning empty array')]
    public function testParseContextKeyWithNoDot(): void
    {
        $result = $this->resolver->parseContextKey('product');

        static::assertSame([], $result);
    }

    #[TestDox('resolves empty path by returning data as-is')]
    public function testResolvePathWithEmptyPathReturnsDataAsIs(): void
    {
        $struct = new StubPathStruct('hello');

        static::assertSame($struct, $this->resolver->resolvePath($struct, [], false, 'product', 'elem-1'));
        static::assertNull($this->resolver->resolvePath(null, [], false, 'product', 'elem-1'));
    }

    #[TestDox('resolves single-segment path on a Struct, returning the property value')]
    public function testResolvePathResolvesDirectStructProperty(): void
    {
        $struct = new StubPathStruct('shopware');

        $result = $this->resolver->resolvePath($struct, ['name'], false, 'product.name', 'elem-1');

        static::assertSame('shopware', $result);
    }

    #[TestDox('resolves nested Struct path, returning deeply nested property')]
    public function testResolvePathResolvesNestedStructProperty(): void
    {
        $child = new StubPathStruct('child-name');
        $parent = new StubPathStruct('parent-name', $child);

        $result = $this->resolver->resolvePath($parent, ['child', 'name'], false, 'product.child.name', 'elem-1');

        static::assertSame('child-name', $result);
    }

    #[TestDox('returns null when data is null and path is non-empty and not required')]
    public function testResolvePathWithNullDataAndNotRequiredReturnsNull(): void
    {
        $result = $this->resolver->resolvePath(null, ['cover'], false, 'product.cover', 'elem-1');

        static::assertNull($result);
    }

    #[TestDox('throws when data is null and path is non-empty and required')]
    public function testResolvePathWithNullDataAndRequiredThrows(): void
    {
        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.cover',
            'elem-1',
            'Base context data is null'
        ));

        $this->resolver->resolvePath(null, ['cover'], true, 'product.cover', 'elem-1');
    }

    #[TestDox('returns null for missing property when not required')]
    public function testResolvePathMissingPropertyNotRequiredReturnsNull(): void
    {
        $struct = new StubPathStruct('test');

        $result = $this->resolver->resolvePath($struct, ['missing'], false, 'product.missing', 'elem-1');

        static::assertNull($result);
    }

    #[TestDox('throws for missing property when required')]
    public function testResolvePathMissingPropertyRequiredThrows(): void
    {
        $struct = new StubPathStruct('test');

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.missing',
            'elem-1',
            'Property \'missing\' does not exist at path \'missing\''
        ));

        $this->resolver->resolvePath($struct, ['missing'], true, 'product.missing', 'elem-1');
    }

    #[TestDox('returns null when intermediate value is not a Struct and not required')]
    public function testResolvePathNonStructIntermediateNotRequiredReturnsNull(): void
    {
        $struct = new StubPathStruct(null, null, 'plain-string');

        $result = $this->resolver->resolvePath($struct, ['nonStructProp', 'deeper'], false, 'product.nonStructProp.deeper', 'elem-1');

        static::assertNull($result);
    }

    #[TestDox('throws when intermediate value is not a Struct and required')]
    public function testResolvePathNonStructIntermediateRequiredThrows(): void
    {
        $struct = new StubPathStruct(null, null, 'plain-string');

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.nonStructProp.deeper',
            'elem-1',
            'Intermediate value at \'nonStructProp\' is not a Struct instance'
        ));

        $this->resolver->resolvePath($struct, ['nonStructProp', 'deeper'], true, 'product.nonStructProp.deeper', 'elem-1');
    }

    #[TestDox('returns null when null intermediate at non-terminal step and not required')]
    public function testResolvePathNullIntermediateNotRequiredReturnsNull(): void
    {
        $struct = new StubPathStruct(null, null);

        $result = $this->resolver->resolvePath($struct, ['child', 'name'], false, 'product.child.name', 'elem-1');

        static::assertNull($result);
    }

    #[TestDox('throws when null intermediate at non-terminal step and required')]
    public function testResolvePathNullIntermediateRequiredThrows(): void
    {
        $struct = new StubPathStruct(null, null);

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.child.name',
            'elem-1',
            'Intermediate value at \'child\' is null'
        ));

        $this->resolver->resolvePath($struct, ['child', 'name'], true, 'product.child.name', 'elem-1');
    }

    #[TestDox('matches identical provider and consumer keys')]
    public function testMatchesReturnsTrueForExactMatch(): void
    {
        static::assertTrue($this->resolver->matches('product', 'product'));
    }

    #[TestDox('matches when consumer key is a subpath of provider key')]
    public function testMatchesReturnsTrueWhenConsumerIsSubpath(): void
    {
        static::assertTrue($this->resolver->matches('product', 'product.cover'));
    }

    #[TestWithJson('["category","product"]')]
    #[TestWithJson('["prod","product"]')]
    #[TestWithJson('["product","products.cover"]')]
    #[TestDox('returns false for non-matching key pair')]
    public function testMatchesReturnsFalseForNonMatchingKeyPair(string $provider, string $consumer): void
    {
        static::assertFalse($this->resolver->matches($provider, $consumer));
    }

    #[TestDox('extracts base key from dotted path')]
    public function testExtractBaseKeyFromDottedPath(): void
    {
        $result = $this->resolver->extractBaseKey('product.cover');

        static::assertSame('product', $result);
    }
}
