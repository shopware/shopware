<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginException::class)]
class PluginExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $e = PluginException::cannotDeleteManaged('MyPlugin');

        static::assertSame(PluginException::CANNOT_DELETE_COMPOSER_MANAGED, $e->getErrorCode());
    }

    public function testCannotExtractNoSuchFile(): void
    {
        $e = PluginException::cannotExtractNoSuchFile('/some/file/that/does/not/exist.zip');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST, $e->getErrorCode());
    }

    public function testCannotExtractInvalidZipFile(): void
    {
        $e = PluginException::cannotExtractInvalidZipFile('/some/invalid.zip');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP_INVALID_ZIP, $e->getErrorCode());
    }

    public function testCannotExtractZipOpenError(): void
    {
        $e = PluginException::cannotExtractZipOpenError('/some/problematic.zip');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP, $e->getErrorCode());
    }

    public function testNoPluginFoundInZip(): void
    {
        $e = PluginException::noPluginFoundInZip('/no/plugin.zip');

        static::assertSame(PluginException::NO_PLUGIN_IN_ZIP, $e->getErrorCode());
    }

    public function testStoreNotAvailable(): void
    {
        $e = PluginException::storeNotAvailable();

        static::assertSame(PluginException::STORE_NOT_AVAILABLE, $e->getErrorCode());
    }

    public function testProjectDirNotInContainer(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('Container parameter "kernel.project_dir" needs to be of type "string"');

        throw PluginException::projectDirNotInContainer();
    }

    public function testCannotDeleteShopwareMigrations(): void
    {
        $e = PluginException::cannotDeleteShopwareMigrations();

        static::assertSame(PluginException::CANNOT_DELETE_SHOPWARE_MIGRATIONS, $e->getErrorCode());
    }

    public function testCouldNotDetectComposerVersion(): void
    {
        $e = PluginException::couldNotDetectComposerVersion(['foo/bar' => '/var/www/shopware/custom/plugins/fooBar/vendor/composer/../../']);

        static::assertSame(PluginException::COULD_NOT_DETECT_COMPOSER_VERSION, $e->getErrorCode());
        static::assertSame("Could not detect the installed composer version. Checked paths: \nfoo/bar: /var/www/shopware/custom/plugins/fooBar/vendor/composer/../../\n", $e->getMessage());
    }

    public function testPluginComposerRequire(): void
    {
        $e = PluginException::pluginComposerRequire('FooBar', 'foo/bar:1.0.0', 'wrong version');
        static::assertSame("Could not execute \"composer require\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $e->getMessage());
    }

    public function testPluginComposerRemove(): void
    {
        $e = PluginException::pluginComposerRemove('FooBar', 'foo/bar:1.0.0', 'wrong version');
        static::assertSame("Could not execute \"composer remove\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $e->getMessage());
    }
}
