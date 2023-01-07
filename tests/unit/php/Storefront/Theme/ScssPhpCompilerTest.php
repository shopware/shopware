<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssPhpCompiler;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\ScssPhpCompiler
 */
class ScssPhpCompilerTest extends TestCase
{
    public function testCompilesEmptyConfig(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            '$background: #123456; background-color: $background;'
        );

        static::assertEquals('background-color: #123456; ', preg_replace('/\r?\n$/', ' ', $compiled), $compiled);
    }

    public function testCompilesWithConfig(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration(
                [
                    'importPaths' => [''],
                    'outputStyle' => OutputStyle::COMPRESSED,
                ]
            ),
            '$background: #123456; background-color: $background;'
        );

        static::assertEquals('background-color:#123456', preg_replace('/\r?\n$/', ' ', $compiled), $compiled);
    }

    public function testFilesHandlesInternalFalse(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        static::assertFalse($scssCompiler->filesHandledInternal());
    }
}
