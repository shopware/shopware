<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ScssPhpCompiler::class)]
class ScssPhpCompilerTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('compilationProvider')]
    #[TestDox('compiles SCSS source for $_dataName')]
    public function testCompilesScss(array $config, string $scss, string $expected): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(new CompilerConfiguration($config), $scss);

        static::assertSame($expected, trim((string) preg_replace('/\r?\n\s*/', ' ', $compiled)));
    }

    #[TestDox('does not inherit output style or import paths across consecutive compiles')]
    public function testConsecutiveCompilesDoNotLeakState(): void
    {
        $scssCompiler = new ScssPhpCompiler();
        $scss = '$background: #123456; body { background-color: $background; }';

        $scssCompiler->compileString(
            new CompilerConfiguration(['outputStyle' => OutputStyle::COMPRESSED]),
            $scss
        );
        $compiled = $scssCompiler->compileString(new CompilerConfiguration([]), $scss);

        static::assertSame(
            'body { background-color: #123456; }',
            trim((string) preg_replace('/\r?\n\s*/', ' ', $compiled))
        );
    }

    #[TestDox('compiles from the file path, ignoring the inline scss, when the path exists')]
    public function testCompilesScssFromExistingFilePath(): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            'body { color: #000000; }',
            __DIR__ . '/fixtures/ScssPhpCompiler/from-file.scss'
        );

        static::assertSame('body { background-color: #123456; }', trim((string) preg_replace('/\r?\n\s*/', ' ', $compiled)));
    }

    #[TestDox('falls back to the inline scss when the given path does not exist')]
    public function testFallsBackToInlineScssWhenPathMissing(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);

        $scssCompiler = new ScssPhpCompiler(filesystem: $filesystem);

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration([]),
            '$background: #123456; body { background-color: $background; }',
            'any/missing/path.scss'
        );

        static::assertSame('body { background-color: #123456; }', trim((string) preg_replace('/\r?\n\s*/', ' ', $compiled)));
    }

    public static function compilationProvider(): \Generator
    {
        yield 'empty config (default expanded output)' => [
            [],
            '$background: #123456; body { background-color: $background; }',
            'body { background-color: #123456; }',
        ];

        yield 'compressed output style' => [
            ['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []],
            '$background: #123456; body { background-color: $background; }',
            'body{background-color:#123456}',
        ];
    }
}
