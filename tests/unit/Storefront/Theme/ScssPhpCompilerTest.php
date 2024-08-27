<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssPhpCompiler;

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
                    'importPaths' => [getcwd()],
                    'outputStyle' => OutputStyle::COMPRESSED,
                ]
            ),
            '$background: #123456; background-color: $background;'
        );

        static::assertEquals('background-color:#123456', preg_replace('/\r?\n$/', ' ', $compiled), $compiled);
    }
}
