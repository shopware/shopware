<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(PluginException::class)]
class PluginExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $e = PluginException::cannotDeleteManaged('MyPlugin');

        static::assertEquals(PluginException::CANNOT_DELETE_COMPOSER_MANAGED, $e->getErrorCode());
    }

    public function testCannotExtractNoSuchFile(): void
    {
        $e = PluginException::cannotExtractNoSuchFile('/some/file/that/does/not/exist.zip');

        static::assertEquals(PluginException::CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST, $e->getErrorCode());
    }

    public function testCannotExtractInvalidZipFile(): void
    {
        $e = PluginException::cannotExtractInvalidZipFile('/some/invalid.zip');

        static::assertEquals(PluginException::CANNOT_EXTRACT_ZIP_INVALID_ZIP, $e->getErrorCode());
    }

    public function testCannotExtractZipOpenError(): void
    {
        $e = PluginException::cannotExtractZipOpenError('/some/problematic.zip');

        static::assertEquals(PluginException::CANNOT_EXTRACT_ZIP, $e->getErrorCode());
    }

    public function testNoPluginFoundInZip(): void
    {
        $e = PluginException::noPluginFoundInZip('/no/plugin.zip');

        static::assertEquals(PluginException::NO_PLUGIN_IN_ZIP, $e->getErrorCode());
    }

    public function testStoreNotAvailable(): void
    {
        $e = PluginException::storeNotAvailable();

        static::assertEquals(PluginException::STORE_NOT_AVAILABLE, $e->getErrorCode());
    }

    public function testProjectDirNotInContainer(): void
    {
        static::expectException(PluginException::class);
        static::expectExceptionMessage('Container parameter "kernel.project_dir" needs to be a string');

        throw PluginException::projectDirNotInContainer();
    }
}
