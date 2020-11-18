<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Util;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;

class PluginFinderTest extends TestCase
{
    public function testFailsOnMissingRootComposerFile(): void
    {
        $errors = new ExceptionCollection();
        (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__,
            TEST_PROJECT_DIR,
            $errors,
            new NullIO()
        );

        static::assertInstanceOf(PluginComposerJsonInvalidException::class, $errors->first());
    }

    public function testLocalLoadsTheComposerJsonContents(): void
    {
        $plugins = (new PluginFinder(new PackageProvider()))->findPlugins(
            __DIR__ . '/_fixture',
            TEST_PROJECT_DIR,
            new ExceptionCollection(),
            new NullIO()
        );
        static::assertCount(1, $plugins);
        static::assertSame($plugins['Works\Works']->getBaseClass(), 'Works\Works');
    }
}
