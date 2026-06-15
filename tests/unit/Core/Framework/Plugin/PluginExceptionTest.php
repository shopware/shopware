<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\KernelPluginLoaderException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerRemoveException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerRequireException;
use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginException::class)]
class PluginExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $exception = PluginException::cannotDeleteManaged('MyPlugin');

        static::assertSame(PluginException::CANNOT_DELETE_COMPOSER_MANAGED, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Plugin MyPlugin is managed by Composer and cannot be deleted', $exception->getMessage());
        static::assertSame(['name' => 'MyPlugin'], $exception->getParameters());
    }

    public function testCannotExtractNoSuchFile(): void
    {
        $exception = PluginException::cannotExtractNoSuchFile('/some/file/that/does/not/exist.zip');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('No such zip file: /some/file/that/does/not/exist.zip', $exception->getMessage());
        static::assertSame(['file' => '/some/file/that/does/not/exist.zip'], $exception->getParameters());
    }

    public function testCannotExtractInvalidZipFile(): void
    {
        $exception = PluginException::cannotExtractInvalidZipFile('/some/invalid.zip');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP_INVALID_ZIP, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('/some/invalid.zip is not a zip archive.', $exception->getMessage());
        static::assertSame(['file' => '/some/invalid.zip'], $exception->getParameters());
    }

    public function testCannotExtractZipOpenError(): void
    {
        $exception = PluginException::cannotExtractZipOpenError('Zip open failed');

        static::assertSame(PluginException::CANNOT_EXTRACT_ZIP, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Zip open failed', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testNoPluginFoundInZip(): void
    {
        $exception = PluginException::noPluginFoundInZip('/no/plugin.zip');

        static::assertSame(PluginException::NO_PLUGIN_IN_ZIP, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('No plugin was found in the zip archive: /no/plugin.zip', $exception->getMessage());
        static::assertSame(['archive' => '/no/plugin.zip'], $exception->getParameters());
    }

    public function testStoreNotAvailable(): void
    {
        $exception = PluginException::storeNotAvailable();

        static::assertSame(PluginException::STORE_NOT_AVAILABLE, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Store is not available', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testCannotCreateTemporaryDirectory(): void
    {
        $exception = PluginException::cannotCreateTemporaryDirectory('/tmp/shopware', 'plugin');

        static::assertSame(PluginException::CANNOT_CREATE_TEMPORARY_DIRECTORY, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Could not create temporary directory in "/tmp/shopware" with prefix "plugin"', $exception->getMessage());
        static::assertSame(['targetDirectory' => '/tmp/shopware', 'prefix' => 'plugin'], $exception->getParameters());
    }

    public function testProjectDirNotInContainer(): void
    {
        $exception = PluginException::projectDirNotInContainer();

        static::assertSame(PluginException::PLUGIN_INVALID_CONTAINER_PARAMETER, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Container parameter "kernel.project_dir" needs to be of type "string"', $exception->getMessage());
        static::assertSame(['name' => 'kernel.project_dir', 'type' => 'string'], $exception->getParameters());
    }

    public function testProjectDirNotInContainerUsesLegacyBehaviourBeforeV68(): void
    {
        $exception = Feature::fake([], static fn () => Feature::silent(
            'v6.8.0.0',
            static fn () => PluginException::projectDirNotInContainer()
        ));

        static::assertInstanceOf(PluginException::class, $exception);
        static::assertSame(PluginException::PROJECT_DIR_IS_NOT_A_STRING, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Container parameter "kernel.project_dir" needs to be a string', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testInvalidContainerParameter(): void
    {
        $exception = PluginException::invalidContainerParameter('kernel.project_dir', 'string');

        static::assertSame(PluginException::PLUGIN_INVALID_CONTAINER_PARAMETER, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Container parameter "kernel.project_dir" needs to be of type "string"', $exception->getMessage());
        static::assertSame(['name' => 'kernel.project_dir', 'type' => 'string'], $exception->getParameters());
    }

    public function testCannotDeleteShopwareMigrations(): void
    {
        $exception = PluginException::cannotDeleteShopwareMigrations();

        static::assertSame(PluginException::CANNOT_DELETE_SHOPWARE_MIGRATIONS, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Deleting Shopware migrations is not allowed', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testNotFound(): void
    {
        $exception = PluginException::notFound('MissingPlugin');

        static::assertSame('FRAMEWORK__PLUGIN_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame('Plugin by name "MissingPlugin" not found.', $exception->getMessage());
        static::assertSame(['name' => 'MissingPlugin'], $exception->getParameters());
    }

    public function testNotInstalled(): void
    {
        $exception = PluginException::notInstalled('MissingPlugin');

        static::assertSame('FRAMEWORK__PLUGIN_NOT_INSTALLED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Plugin "MissingPlugin" is not installed.', $exception->getMessage());
        static::assertSame(['plugin' => 'MissingPlugin'], $exception->getParameters());
    }

    public function testNotActivated(): void
    {
        $exception = PluginException::notActivated('InactivePlugin');

        static::assertSame('FRAMEWORK__PLUGIN_NOT_ACTIVATED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Plugin "InactivePlugin" is not activated.', $exception->getMessage());
        static::assertSame(['plugin' => 'InactivePlugin'], $exception->getParameters());
    }

    public function testHasActiveDependants(): void
    {
        $dependant = new PluginEntity();
        $dependant->setName('DependentPlugin');

        $exception = PluginException::hasActiveDependants('BasePlugin', [$dependant]);

        static::assertSame('FRAMEWORK__PLUGIN_HAS_DEPENDANTS', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('The following plugins depend on "BasePlugin": "DependentPlugin". They need to be deactivated before "BasePlugin" can be deactivated or uninstalled itself.', $exception->getMessage());
        static::assertSame(['dependency' => 'BasePlugin', 'dependants' => [$dependant], 'dependantNames' => '"DependentPlugin"'], $exception->getParameters());
    }

    public function testComposerJsonInvalid(): void
    {
        $exception = PluginException::composerJsonInvalid('/plugins/Foo/composer.json', ['name : The property name is required']);

        static::assertSame('FRAMEWORK__PLUGIN_COMPOSER_JSON_INVALID', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame("The file \"/plugins/Foo/composer.json\" is invalid. Errors:\nname : The property name is required", $exception->getMessage());
        static::assertSame([
            'composerJsonPath' => '/plugins/Foo/composer.json',
            'errorsString' => 'name : The property name is required',
            'errors' => ['name : The property name is required'],
        ], $exception->getParameters());
    }

    public function testBaseClassNotFound(): void
    {
        $exception = PluginException::baseClassNotFound('Foo\\Bar');

        static::assertSame('FRAMEWORK__PLUGIN_BASE_CLASS_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('The class "Foo\\Bar" is not found. Probably a class loader error. Check your plugin composer.json', $exception->getMessage());
        static::assertSame(['baseClass' => 'Foo\\Bar'], $exception->getParameters());
    }

    public function testFailedKernelReboot(): void
    {
        $exception = PluginException::failedKernelReboot();

        static::assertSame(PluginException::PLUGIN_KERNEL_REBOOT_FAILED, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Failed to reboot the kernel', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testWrongBaseClass(): void
    {
        $exception = PluginException::wrongBaseClass('Foo\\Bar');

        static::assertSame(PluginException::PLUGIN_WRONG_BASE_CLASS, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('"Foo\\Bar" in the container should be an instance of Shopware\Core\Framework\Plugin', $exception->getMessage());
        static::assertSame(['baseClass' => 'Foo\\Bar'], $exception->getParameters());
    }

    public function testComposerJsonMissing(): void
    {
        $exception = PluginException::composerJsonMissing('MissingComposerPlugin', '/plugins/MissingComposerPlugin/composer.json');

        static::assertSame(PluginException::PLUGIN_COMPOSER_JSON_MISSING, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Plugin "MissingComposerPlugin" has no composer.json at "/plugins/MissingComposerPlugin/composer.json".', $exception->getMessage());
        static::assertSame(['pluginName' => 'MissingComposerPlugin', 'composerJsonPath' => '/plugins/MissingComposerPlugin/composer.json'], $exception->getParameters());
    }

    public function testCouldNotDetectComposerVersion(): void
    {
        $exception = PluginException::couldNotDetectComposerVersion(['foo/bar' => '/var/www/shopware/custom/plugins/fooBar/vendor/composer/../../']);

        static::assertSame(PluginException::COULD_NOT_DETECT_COMPOSER_VERSION, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame("Could not detect the installed composer version. Checked paths: \nfoo/bar: /var/www/shopware/custom/plugins/fooBar/vendor/composer/../../\n", $exception->getMessage());
        static::assertSame(['checkedPaths' => "\nfoo/bar: /var/www/shopware/custom/plugins/fooBar/vendor/composer/../../\n"], $exception->getParameters());
    }

    public function testPluginComposerRequire(): void
    {
        $exception = PluginException::pluginComposerRequire('FooBar', 'foo/bar:1.0.0', 'wrong version');

        static::assertInstanceOf(PluginException::class, $exception);
        static::assertSame(PluginException::PLUGIN_COMPOSER_REQUIRE, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame("Could not execute \"composer require\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $exception->getMessage());
        static::assertSame(['pluginName' => 'FooBar', 'pluginComposerName' => 'foo/bar:1.0.0', 'output' => 'wrong version'], $exception->getParameters());
    }

    public function testPluginComposerRequireUsesLegacyExceptionBeforeV68(): void
    {
        $exception = Feature::fake([], static fn () => Feature::silent(
            'v6.8.0.0',
            static fn () => PluginException::pluginComposerRequire('FooBar', 'foo/bar:1.0.0', 'wrong version')
        ));

        static::assertInstanceOf(PluginComposerRequireException::class, $exception);
        static::assertSame(PluginException::PLUGIN_COMPOSER_REQUIRE, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame("Could not execute \"composer require\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $exception->getMessage());
        static::assertSame(['pluginName' => 'FooBar', 'pluginComposerName' => 'foo/bar:1.0.0', 'output' => 'wrong version'], $exception->getParameters());
    }

    public function testPluginComposerRemove(): void
    {
        $exception = PluginException::pluginComposerRemove('FooBar', 'foo/bar:1.0.0', 'wrong version');

        static::assertInstanceOf(PluginException::class, $exception);
        static::assertSame(PluginException::PLUGIN_COMPOSER_REMOVE, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame("Could not execute \"composer remove\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $exception->getMessage());
        static::assertSame(['pluginName' => 'FooBar', 'pluginComposerName' => 'foo/bar:1.0.0', 'output' => 'wrong version'], $exception->getParameters());
    }

    public function testPluginComposerRemoveUsesLegacyExceptionBeforeV68(): void
    {
        $exception = Feature::fake([], static fn () => Feature::silent(
            'v6.8.0.0',
            static fn () => PluginException::pluginComposerRemove('FooBar', 'foo/bar:1.0.0', 'wrong version')
        ));

        static::assertInstanceOf(PluginComposerRemoveException::class, $exception);
        static::assertSame(PluginException::PLUGIN_COMPOSER_REMOVE, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame("Could not execute \"composer remove\" for plugin \"FooBar (foo/bar:1.0.0). Output:\nwrong version", $exception->getMessage());
        static::assertSame(['pluginName' => 'FooBar', 'pluginComposerName' => 'foo/bar:1.0.0', 'output' => 'wrong version'], $exception->getParameters());
    }

    public function testKernelPluginLoaderError(): void
    {
        $exception = PluginException::kernelPluginLoaderError('FooPlugin', 'class missing');

        static::assertInstanceOf(PluginException::class, $exception);
        static::assertSame(PluginException::KERNEL_PLUGIN_LOADER_ERROR, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Failed to load plugin "FooPlugin". Reason: class missing', $exception->getMessage());
        static::assertSame(['plugin' => 'FooPlugin', 'reason' => 'class missing'], $exception->getParameters());
    }

    public function testKernelPluginLoaderErrorUsesLegacyExceptionBeforeV68(): void
    {
        $exception = Feature::fake([], static fn () => Feature::silent(
            'v6.8.0.0',
            static fn () => PluginException::kernelPluginLoaderError('FooPlugin', 'class missing')
        ));

        static::assertInstanceOf(KernelPluginLoaderException::class, $exception);
        static::assertSame(PluginException::KERNEL_PLUGIN_LOADER_ERROR, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Failed to load plugin "FooPlugin". Reason: class missing', $exception->getMessage());
        static::assertSame(['plugin' => 'FooPlugin', 'reason' => 'class missing'], $exception->getParameters());
    }

    public function testPluginExtractionError(): void
    {
        $exception = PluginException::pluginExtractionError('archive broken');

        static::assertInstanceOf(PluginExtractionException::class, $exception);
        static::assertSame(PluginException::PLUGIN_EXTRACTION_FAILED, $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Plugin extraction failed. Error: archive broken', $exception->getMessage());
        static::assertSame(['error' => 'archive broken'], $exception->getParameters());
    }

    public function testInvalidPluginCreationInputError(): void
    {
        $exception = PluginException::invalidPluginCreationInputError('invalid name');

        static::assertSame(PluginException::PLUGIN_CREATION_INVALID_ENTRY, $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Invalid input provided during plugin creation. Error: invalid name', $exception->getMessage());
        static::assertSame(['reason' => 'invalid name'], $exception->getParameters());
    }
}
