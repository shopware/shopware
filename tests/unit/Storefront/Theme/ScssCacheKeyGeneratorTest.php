<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\ScssCacheKeyGenerator;

/**
 * @internal
 */
#[CoversClass(ScssCacheKeyGenerator::class)]
class ScssCacheKeyGeneratorTest extends TestCase
{
    private Filesystem $filesystem;

    private ScssCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->generator = new ScssCacheKeyGenerator($this->filesystem);
    }

    #[TestDox('produces a stable, prefixed key for identical inputs')]
    public function testGenerateIsStableForIdenticalInputs(): void
    {
        $key1 = $this->generator->generate('body{color:red}', null, 'compressed', []);
        $key2 = $this->generator->generate('body{color:red}', null, 'compressed', []);

        static::assertSame($key1, $key2);
        static::assertStringStartsWith('scss_compiler_', $key1);
    }

    /**
     * @param array{0: string, 1: ?string, 2: string, 3: array<int|string, mixed>} $argsA
     * @param array{0: string, 1: ?string, 2: string, 3: array<int|string, mixed>} $argsB
     */
    #[DataProvider('changingInputProvider')]
    #[TestDox('produces a different key when $_dataName changes')]
    public function testGenerateChangesWhenInputDiffers(array $argsA, array $argsB): void
    {
        $a = $this->generator->generate(...$argsA);
        $b = $this->generator->generate(...$argsB);

        static::assertNotSame($a, $b);
    }

    public static function changingInputProvider(): \Generator
    {
        yield 'scss source' => [
            ['body{color:red}', null, 'compressed', []],
            ['body{color:blue}', null, 'compressed', []],
        ];

        yield 'output style' => [
            ['body{color:red}', null, 'compressed', []],
            ['body{color:red}', null, 'expanded', []],
        ];

        yield 'path' => [
            ['body{color:red}', '/some/path/a', 'compressed', []],
            ['body{color:red}', '/some/path/b', 'compressed', []],
        ];

        yield 'import path closure identity' => [
            ['body{color:red}', null, 'compressed', [static fn () => 'a']],
            ['body{color:red}', null, 'compressed', [static fn () => 'b']],
        ];

        yield 'import path non-closure object identity' => [
            ['body{color:red}', null, 'compressed', [new \stdClass()]],
            ['body{color:red}', null, 'compressed', [new \stdClass()]],
        ];

        yield 'import path nested array contents' => [
            ['body{color:red}', null, 'compressed', [['a', 'b']]],
            ['body{color:red}', null, 'compressed', [['c', 'd']]],
        ];
    }

    #[TestDox('includes the entry-point mtime in the key when the path exists on disk')]
    public function testGenerateIncludesPathMtimeWhenPathExists(): void
    {
        $this->filesystem->write('/theme/all.scss', 'body{color:red}', ['timestamp' => 1000]);
        $a = $this->generator->generate('body{color:red}', '/theme/all.scss', 'compressed', []);

        $this->filesystem->write('/theme/all.scss', 'body{color:red}', ['timestamp' => 2000]);
        $b = $this->generator->generate('body{color:red}', '/theme/all.scss', 'compressed', []);

        static::assertNotSame($a, $b);
    }

    #[TestDox('changes the key when the mtime of an imported file changes')]
    public function testGenerateChangesWhenImportedFileMtimeChanges(): void
    {
        $this->filesystem->write('/base/_partial.scss', '.x{color:red}', ['timestamp' => 1000]);
        $scss = '@import "partial";';

        $first = $this->generator->generate($scss, null, 'compressed', ['/base']);

        $this->filesystem->write('/base/_partial.scss', '.x{color:red}', ['timestamp' => 2000]);
        $second = $this->generator->generate($scss, null, 'compressed', ['/base']);

        static::assertNotSame($first, $second);
    }

    #[DataProvider('moduleDirectiveProvider')]
    #[TestDox('resolves $_dataName statements against the configured base paths')]
    public function testFindImportsResolvesModuleDirectives(string $scss): void
    {
        $this->filesystem->write('/base/_button.scss', '.btn{color:red}');

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertSame(['/base/_button.scss'], $imports);
    }

    public static function moduleDirectiveProvider(): \Generator
    {
        yield '@import' => ['@import "button";'];
        yield '@use' => ['@use "button";'];
        yield '@forward' => ['@forward "button";'];
        yield '@use with alias' => ['@use "button" as btn;'];
        yield '@use with config' => ['@use "button" with ($primary: red);'];
        yield '@forward with show' => ['@forward "button" show $primary;'];
    }

    #[TestDox('walks nested @import chains recursively')]
    public function testFindImportsFollowsNestedImports(): void
    {
        $this->filesystem->write('/base/_root.scss', '@import \'leaf\';');
        $this->filesystem->write('/base/_leaf.scss', '.leaf{color:green}');
        $scss = '@import "root";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertContains('/base/_root.scss', $imports);
        static::assertContains('/base/_leaf.scss', $imports);
    }

    #[TestDox('resolves absolute @import paths even with no base paths configured')]
    public function testFindImportsResolvesAbsolutePathsWithoutBase(): void
    {
        $this->filesystem->write('/abs/abs.scss', '.abs{}');
        $scss = '@import \'/abs/abs.scss\';';

        $imports = $this->generator->findImports($scss, []);

        static::assertSame(['/abs/abs.scss'], $imports);
    }

    #[TestDox('terminates instead of looping when @import chains are circular')]
    public function testFindImportsBreaksOnCircularImports(): void
    {
        $this->filesystem->write('/base/_a.scss', '@import \'b\';');
        $this->filesystem->write('/base/_b.scss', '@import \'a\';');
        $scss = '@import "a";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertCount(2, $imports);
    }

    #[TestDox('silently skips @import targets that cannot be resolved')]
    public function testFindImportsIgnoresUnresolvableImports(): void
    {
        $scss = '@import "missing";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertSame([], $imports);
    }

    #[TestDox('treats a fileExists() FilesystemException as a missing file')]
    public function testFindImportsTreatsFileExistsExceptionAsMissing(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')
            ->willThrowException(UnableToCheckFileExistence::forLocation('/base/button.scss'));

        $generator = new ScssCacheKeyGenerator($filesystem);

        static::assertSame([], $generator->findImports('@import "button";', ['/base']));
    }

    #[TestDox('records the import but stops recursing when read() throws a FilesystemException')]
    public function testFindImportsStopsRecursingWhenReadThrows(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('read')
            ->willThrowException(UnableToReadFile::fromLocation('/base/button.scss'));

        $generator = new ScssCacheKeyGenerator($filesystem);

        static::assertSame(['/base/button.scss'], $generator->findImports('@import "button";', ['/base']));
    }

    #[TestDox('falls back to a zero mtime when lastModified() throws a FilesystemException')]
    public function testGenerateUsesZeroMtimeWhenLastModifiedThrows(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('lastModified')
            ->willThrowException(UnableToRetrieveMetadata::lastModified('/theme/all.scss'));

        $generator = new ScssCacheKeyGenerator($filesystem);

        $key = $generator->generate('body{color:red}', '/theme/all.scss', 'compressed', []);

        static::assertStringStartsWith('scss_compiler_', $key);
    }

    #[TestDox('reduces a resource import-path to a stable type label, so distinct resources share a key')]
    public function testGenerateUsesStableLabelForResourceImportPaths(): void
    {
        $streamA = fopen('php://memory', 'rb');
        $streamB = fopen('php://memory', 'rb');
        static::assertIsResource($streamA);
        static::assertIsResource($streamB);

        try {
            $a = $this->generator->generate('body{color:red}', null, 'compressed', [$streamA]);
            $b = $this->generator->generate('body{color:red}', null, 'compressed', [$streamB]);
        } finally {
            fclose($streamA);
            fclose($streamB);
        }

        static::assertSame($a, $b);
    }
}
