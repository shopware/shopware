<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\CachedScssCompiler;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssCacheKeyGenerator;
use Shopware\Tests\Unit\Storefront\Theme\Stub\CountingScssCompilerStub;
use Shopware\Tests\Unit\Storefront\Theme\Stub\StubOutputStyle;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[CoversClass(CachedScssCompiler::class)]
class CachedScssCompilerTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('cacheBypassProvider')]
    #[TestDox('bypasses the cache when useCache is $_dataName')]
    public function testBypassesCacheWhenUseCacheIsNotTrue(array $config): void
    {
        $inner = new CountingScssCompilerStub();
        $compiler = new CachedScssCompiler(
            $inner,
            new TagAwareAdapter(new ArrayAdapter()),
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        $compilerConfig = new CompilerConfiguration($config);

        $compiler->compileString($compilerConfig, 'body{color:red}');
        $compiler->compileString($compilerConfig, 'body{color:red}');

        static::assertSame(2, $inner->calls);
    }

    public static function cacheBypassProvider(): \Generator
    {
        yield 'absent' => [[]];
        yield 'false' => [['useCache' => false]];
    }

    #[TestDox('reuses the cached result for identical compilations when useCache is true')]
    public function testCachesIdenticalCompilationsWhenUseCacheIsTrue(): void
    {
        $inner = new CountingScssCompilerStub();
        $compiler = new CachedScssCompiler(
            $inner,
            new TagAwareAdapter(new ArrayAdapter()),
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        $config = new CompilerConfiguration(['useCache' => true, 'outputStyle' => OutputStyle::COMPRESSED]);

        $first = $compiler->compileString($config, 'body{color:red}');
        $second = $compiler->compileString($config, 'body{color:red}');

        static::assertSame($first, $second);
        static::assertSame(1, $inner->calls);
    }

    #[TestDox('uses distinct cache entries for different SCSS sources')]
    public function testCacheKeyVariesPerSource(): void
    {
        $inner = new CountingScssCompilerStub();
        $compiler = new CachedScssCompiler(
            $inner,
            new TagAwareAdapter(new ArrayAdapter()),
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        $config = new CompilerConfiguration(['useCache' => true, 'outputStyle' => OutputStyle::COMPRESSED]);

        $compiler->compileString($config, 'body{color:red}');
        $compiler->compileString($config, 'body{color:blue}');

        static::assertSame(2, $inner->calls);
    }

    #[TestDox('writes lifetime and tags onto cache items so tag invalidation evicts them')]
    public function testWritesLifetimeAndTagsOnCacheItem(): void
    {
        $inner = new CountingScssCompilerStub();
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $compiler = new CachedScssCompiler(
            $inner,
            $cache,
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
            ['lifetime' => 60, 'tags' => ['scss_compiler']],
        );

        $compiler->compileString(
            new CompilerConfiguration(['useCache' => true, 'outputStyle' => OutputStyle::COMPRESSED]),
            'body{color:red}',
        );

        $cache->invalidateTags(['scss_compiler']);

        $compiler->compileString(
            new CompilerConfiguration(['useCache' => true, 'outputStyle' => OutputStyle::COMPRESSED]),
            'body{color:red}',
        );

        static::assertSame(2, $inner->calls);
    }

    #[TestDox('falls back to the compressed output style when none is configured')]
    public function testAbsentOutputStyleResolvesToCompressed(): void
    {
        $inner = new CountingScssCompilerStub();
        $compiler = new CachedScssCompiler(
            $inner,
            new TagAwareAdapter(new ArrayAdapter()),
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        // No outputStyle in the config: resolveOutputStyle() falls back to its COMPRESSED default,
        // so this must land on the same cache entry as an explicitly COMPRESSED compilation.
        $compiler->compileString(new CompilerConfiguration(['useCache' => true]), 'body{color:red}');
        $compiler->compileString(
            new CompilerConfiguration(['useCache' => true, 'outputStyle' => OutputStyle::COMPRESSED]),
            'body{color:red}',
        );

        static::assertSame(1, $inner->calls);
    }

    #[TestDox('reads the value from a backed-enum output style for the cache key')]
    public function testBackedEnumOutputStyleResolvesToItsValue(): void
    {
        $inner = new CountingScssCompilerStub();
        $compiler = new CachedScssCompiler(
            $inner,
            new TagAwareAdapter(new ArrayAdapter()),
            new ScssCacheKeyGenerator(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        // A backed-enum output style must resolve to its ->value, so it shares a cache entry
        // with the equivalent string output style. This is the path scssphp 2.x takes, where
        // OutputStyle is a backed enum rather than the string-constant class used here on 1.x.
        $compiler->compileString(
            new CompilerConfiguration(['useCache' => true, 'outputStyle' => StubOutputStyle::Compressed]),
            'body{color:red}',
        );
        $compiler->compileString(
            new CompilerConfiguration(['useCache' => true, 'outputStyle' => 'compressed']),
            'body{color:red}',
        );

        static::assertSame(1, $inner->calls);
    }
}
