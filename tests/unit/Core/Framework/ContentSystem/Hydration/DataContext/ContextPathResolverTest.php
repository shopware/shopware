<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\StubPathStruct;

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

    /**
     * @param list<string> $path
     */
    #[DataProvider('unresolvedPathNotRequiredProvider')]
    #[TestDox('returns null for unresolvable path when not required: $_dataName')]
    public function testResolvePathUnresolvableNotRequiredReturnsNull(?Struct $data, array $path, string $fullPath): void
    {
        $result = $this->resolver->resolvePath($data, $path, false, $fullPath, 'elem-1');

        static::assertNull($result);
    }

    /**
     * @return iterable<string, array{Struct|null, list<string>, string}>
     */
    public static function unresolvedPathNotRequiredProvider(): iterable
    {
        yield 'null base data' => [null, ['cover'], 'product.cover'];
        yield 'missing property on struct' => [new StubPathStruct('test'), ['missing'], 'product.missing'];
        yield 'non-struct intermediate value' => [new StubPathStruct(null, null, 'plain-string'), ['nonStructProp', 'deeper'], 'product.nonStructProp.deeper'];
        yield 'null intermediate value' => [new StubPathStruct(null, null), ['child', 'name'], 'product.child.name'];
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('unresolvedPathRequiredProvider')]
    #[TestDox('throws for unresolvable path when required: $_dataName')]
    public function testResolvePathUnresolvableRequiredThrows(?Struct $data, array $path, string $fullPath, ContentSystemException $exception): void
    {
        $this->expectExceptionObject($exception);

        $this->resolver->resolvePath($data, $path, true, $fullPath, 'elem-1');
    }

    /**
     * @return iterable<string, array{Struct|null, list<string>, string, ContentSystemException}>
     */
    public static function unresolvedPathRequiredProvider(): iterable
    {
        yield 'null base data' => [
            null, ['cover'], 'product.cover',
            ContentSystemException::contextPathNotResolvable('product.cover', 'elem-1', 'Base context data is null'),
        ];
        yield 'missing property on struct' => [
            new StubPathStruct('test'), ['missing'], 'product.missing',
            ContentSystemException::contextPathNotResolvable('product.missing', 'elem-1', 'Property \'missing\' does not exist at path \'missing\''),
        ];
        yield 'non-struct intermediate value' => [
            new StubPathStruct(null, null, 'plain-string'), ['nonStructProp', 'deeper'], 'product.nonStructProp.deeper',
            ContentSystemException::contextPathNotResolvable('product.nonStructProp.deeper', 'elem-1', 'Intermediate value at \'nonStructProp\' is not a Struct instance'),
        ];
        yield 'null intermediate value' => [
            new StubPathStruct(null, null), ['child', 'name'], 'product.child.name',
            ContentSystemException::contextPathNotResolvable('product.child.name', 'elem-1', 'Intermediate value at \'child\' is null'),
        ];
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
