<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Plugin\Util\PluginFinder
 */
class PluginFinderTest extends TestCase
{
    public function testFailsOnMissingRootComposerFile(): void
    {
        $errors = new ExceptionCollection();
        (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__,
            __DIR__ . '/../../../../../../..',
            $errors,
            new NullIO()
        );

        static::assertInstanceOf(PluginComposerJsonInvalidException::class, $errors->first());
    }

    public function testLocalLoadsTheComposerJsonContents(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );
        static::assertCount(1, $plugins);
        static::assertSame($plugins['Works\Works']->getBaseClass(), 'Works\Works');
    }

    /*
     * Referring to __DIR__ . '/_fixture/', you can see that we have the same plugin installed locally (residing inside
     * the directory for locally installed plugins) and via composer (residing in the vendor directory and being
     * registered in the installed.json). The Pluginfinder should always consider plugin definitions found via composer
     * over those found in the local directory.
     */
    public function testConsidersComposerInstalledPluginsOverLocalInstalledPlugins(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );

        static::assertInstanceOf(PluginFromFileSystemStruct::class, $plugins['Works\Works']);
        static::assertTrue($plugins['Works\Works']->getManagedByComposer());
        // path is still local if it exists
        static::assertEquals(__DIR__ . '/_fixture/LocallyInstalledPlugins/SwagTest', $plugins['Works\Works']->getPath());
        // version info is still from local, as that might be more up to date
        static::assertEquals('v1.0.2', $plugins['Works\Works']->getComposerPackage()->getPrettyVersion());
    }
}
