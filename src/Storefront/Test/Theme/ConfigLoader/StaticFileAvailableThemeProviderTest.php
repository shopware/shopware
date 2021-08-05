<?php
declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider;

class StaticFileAvailableThemeProviderTest extends TestCase
{
    public function testFileNotExisting(): void
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Cannot find theme configuration. Did you run bin/console theme:dump');

        $fs = new Filesystem(new MemoryAdapter());
        $s = new StaticFileAvailableThemeProvider($fs);
        $s->load(Context::createDefaultContext());
    }

    public function testFileExists(): void
    {
        $fs = new Filesystem(new MemoryAdapter());
        $fs->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode(['test' => 'test']));

        $s = new StaticFileAvailableThemeProvider($fs);
        static::assertSame(['test' => 'test'], $s->load(Context::createDefaultContext()));
    }

    public function testCallGetDecoratedThrowsError(): void
    {
        static::expectException(DecorationPatternException::class);

        $fs = new Filesystem(new MemoryAdapter());
        $s = new StaticFileAvailableThemeProvider($fs);
        $s->getDecorated(Context::createDefaultContext());
    }
}
