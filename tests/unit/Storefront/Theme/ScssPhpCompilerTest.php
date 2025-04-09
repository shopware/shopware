<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[CoversClass(ScssPhpCompiler::class)]
class ScssPhpCompilerTest extends TestCase
{
    public function testCompilesEmptyConfig(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            '$background: #123456; body { background-color: $background; }'
        );

        static::assertEquals('body{background-color:#123456;}', preg_replace('/\s+/', '', $compiled), $compiled);
    }

    public function testCompilesWithConfig(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration(
                [
                    'importPaths' => [getcwd()],
                    'outputStyle' => OutputStyle::COMPRESSED,
                ]
            ),
            '$background: #123456; body { background-color: $background; }'
        );

        static::assertEquals('body{background-color:#123456}', preg_replace('/\s+/', '', $compiled), $compiled);
    }

    public function testCompilesWithCache(): void
    {
        // Create a mock cache adapter
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->method('get')->willReturn('cached-css-result');

        // Create compiler with cache options and mock cache
        $scssCompiler = new ScssPhpCompiler(['lifetime' => 3600], $cache);

        // Compile
        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            '$background: #123456; body { background-color: $background; }'
        );

        // Should return cached result
        static::assertEquals('cached-css-result', $compiled);
    }

    public function testCompilesAndSavesToCache(): void
    {
        // Mock cache item
        $item = $this->createMock(ItemInterface::class);
        $item->method('expiresAfter')->willReturnSelf();
        $item->method('tag')->willReturnSelf();

        // Create a mock cache adapter
        $cache = $this->createMock(TagAwareCacheInterface::class);

        // Store item for callback
        $cacheItem = $item;

        // Set up the get method to execute the callback
        $cache->method('get')
            ->willReturnCallback(function (string $cacheKey, callable $cacheCallback) use ($cacheItem) {
                return $cacheCallback($cacheItem);
            });

        // Create compiler with cache options and mock cache
        $scssCompiler = new ScssPhpCompiler(['lifetime' => 3600, 'tags' => ['scss_compiler']], $cache);

        // Compile
        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            '$background: #123456; body { background-color: $background; }'
        );

        // Should compile as normal since we're executing the callback
        static::assertEquals('body{background-color:#123456;}', preg_replace('/\s+/', '', $compiled), $compiled);
    }

    public function testCompilesBypassesCache(): void
    {
        // Create a mock cache adapter
        $cache = $this->createMock(TagAwareCacheInterface::class);

        // The cache->get method should never be called when skipCache is true
        $cache->expects($this->never())->method('get');

        // The delete method should never be called either
        $cache->expects($this->never())->method('delete');

        // Create compiler with cache options and mock cache
        $scssCompiler = new ScssPhpCompiler(['lifetime' => 3600], $cache);

        // Compile with skipCache = true
        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([
                'skipCache' => true,
            ]),
            '$background: #123456; body { background-color: $background; }'
        );

        // Should compile directly without using cache
        static::assertEquals('body{background-color:#123456;}', preg_replace('/\s+/', '', $compiled), $compiled);
    }
}
