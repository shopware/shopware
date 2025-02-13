<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\PluginBaseClassNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginException extends HttpException
{
    public const CANNOT_DELETE_COMPOSER_MANAGED = 'FRAMEWORK__PLUGIN_CANNOT_DELETE_COMPOSER_MANAGED';
    public const CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED_FILE_DOES_NOT_EXIST';
    public const CANNOT_EXTRACT_ZIP_INVALID_ZIP = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED_INVALID_ZIP';
    public const CANNOT_EXTRACT_ZIP = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED';
    public const NO_PLUGIN_IN_ZIP = 'FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP';
    public const STORE_NOT_AVAILABLE = 'FRAMEWORK__STORE_NOT_AVAILABLE';
    public const CANNOT_CREATE_TEMPORARY_DIRECTORY = 'FRAMEWORK__PLUGIN_CANNOT_CREATE_TEMPORARY_DIRECTORY';

    /**
     * @deprecated tag:v6.8.0 - Will be removed with next major, as it is unused
     */
    public const PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';

    public const CANNOT_DELETE_SHOPWARE_MIGRATIONS = 'FRAMEWORK__PLUGIN_CANNOT_DELETE_SHOPWARE_MIGRATIONS';
    public const PLUGIN_INVALID_CONTAINER_PARAMETER = 'FRAMEWORK__PLUGIN_INVALID_CONTAINER_PARAMETER';
    public const PLUGIN_KERNEL_REBOOT_FAILED = 'FRAMEWORK__PLUGIN_KERNEL_REBOOT_FAILED';
    public const PLUGIN_WRONG_BASE_CLASS = 'FRAMEWORK__PLUGIN_WRONG_BASE_CLASS';

    /**
     * @internal will be removed once store extensions are installed over composer
     */
    public static function cannotDeleteManaged(string $pluginName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CANNOT_DELETE_COMPOSER_MANAGED,
            'Plugin {{ name }} is managed by Composer and cannot be deleted',
            ['name' => $pluginName]
        );
    }

    public static function cannotExtractNoSuchFile(string $filename): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST,
            'No such zip file: {{ file }}',
            ['file' => $filename]
        );
    }

    public static function cannotExtractInvalidZipFile(string $filename): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_EXTRACT_ZIP_INVALID_ZIP,
            '{{ file }} is not a zip archive.',
            ['file' => $filename]
        );
    }

    public static function cannotExtractZipOpenError(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_EXTRACT_ZIP,
            $message
        );
    }

    public static function noPluginFoundInZip(string $archive): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_PLUGIN_IN_ZIP,
            'No plugin was found in the zip archive: {{ archive }}',
            ['archive' => $archive]
        );
    }

    public static function storeNotAvailable(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::STORE_NOT_AVAILABLE,
            'Store is not available',
        );
    }

    public static function cannotCreateTemporaryDirectory(string $targetDirectory, string $prefix): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_CREATE_TEMPORARY_DIRECTORY,
            'Could not create temporary directory in "{{ targetDirectory }}" with prefix "{{ prefix }}"',
            ['targetDirectory' => $targetDirectory, 'prefix' => $prefix]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with next major. Use PluginException::invalidContainerParameter instead
     */
    public static function projectDirNotInContainer(): self
    {
        if (!Feature::isActive('v6.8.0.0')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', 'PluginException::invalidContainerParameter')
            );

            return new self(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                self::PROJECT_DIR_IS_NOT_A_STRING,
                'Container parameter "kernel.project_dir" needs to be a string'
            );
        }

        return self::invalidContainerParameter('kernel.project_dir', 'string');
    }

    public static function invalidContainerParameter(string $name, string $expectedType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PLUGIN_INVALID_CONTAINER_PARAMETER,
            'Container parameter "{{ name }}" needs to be of type "{{ type }}"',
            ['name' => $name, 'type' => $expectedType]
        );
    }

    public static function cannotDeleteShopwareMigrations(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_DELETE_SHOPWARE_MIGRATIONS,
            'Deleting Shopware migrations is not allowed'
        );
    }

    public static function notFound(string $name): PluginNotFoundException
    {
        return new PluginNotFoundException($name);
    }

    public static function notInstalled(string $name): PluginNotInstalledException
    {
        return new PluginNotInstalledException($name);
    }

    public static function notActivated(string $name): PluginNotActivatedException
    {
        return new PluginNotActivatedException($name);
    }

    /**
     * @param list<PluginEntity> $dependants
     */
    public static function hasActiveDependants(string $name, array $dependants): PluginHasActiveDependantsException
    {
        return new PluginHasActiveDependantsException($name, $dependants);
    }

    /**
     * @param list<string> $errors
     */
    public static function composerJsonInvalid(string $composerJsonPath, array $errors): PluginComposerJsonInvalidException
    {
        return new PluginComposerJsonInvalidException($composerJsonPath, $errors);
    }

    public static function baseClassNotFound(string $baseClass): PluginBaseClassNotFoundException
    {
        return new PluginBaseClassNotFoundException($baseClass);
    }

    public static function failedKernelReboot(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PLUGIN_KERNEL_REBOOT_FAILED,
            'Failed to reboot the kernel'
        );
    }

    public static function wrongBaseClass(string $pluginBaseClassString): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PLUGIN_WRONG_BASE_CLASS,
            '"{{ baseClass }}" in the container should be an instance of ' . Plugin::class,
            ['baseClass' => $pluginBaseClassString]
        );
    }
}
