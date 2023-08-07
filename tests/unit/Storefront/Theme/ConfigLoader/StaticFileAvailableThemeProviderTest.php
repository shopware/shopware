<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider
 */
class StaticFileAvailableThemeProviderTest extends TestCase
{
    public function testFileNotExisting(): void
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Cannot find theme configuration. Did you run bin/console theme:dump');

        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $s = new StaticFileAvailableThemeProvider($fs);
        $s->load(Context::createDefaultContext(), false);
    }

    public function testFileExists(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode(['test' => 'test'], \JSON_THROW_ON_ERROR));

        $s = new StaticFileAvailableThemeProvider($fs);
        static::assertSame(['test' => 'test'], $s->load(Context::createDefaultContext(), false));
    }

    public function testLoadThrowsExceptionInNextMajorWhenCalledWithOnlyOneParameter(): void
    {
        static::expectException(\RuntimeException::class);

        $s = new StaticFileAvailableThemeProvider(new Filesystem(new InMemoryFilesystemAdapter()));
        static::assertSame(['test' => 'test'], $s->load(Context::createDefaultContext()));
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testLoadCanStillBeCalledWithOneParameter(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode(['test' => 'test'], \JSON_THROW_ON_ERROR));

        $s = new StaticFileAvailableThemeProvider($fs);
        static::assertSame(['test' => 'test'], $s->load(Context::createDefaultContext()));
    }

    public function testCallGetDecoratedThrowsError(): void
    {
        static::expectException(DecorationPatternException::class);

        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $s = new StaticFileAvailableThemeProvider($fs);
        $s->getDecorated();
    }
}
