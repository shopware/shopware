<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class PluginException extends HttpException
{
    public const CANNOT_DELETE_COMPOSER_MANAGED = 'FRAMEWORK__PLUGIN_CANNOT_DELETE_COMPOSER_MANAGED';
    public const CANNOT_EXTRACT_ZIP_FILE_DOES_NOT_EXIST = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED_FILE_DOES_NOT_EXIST';
    public const CANNOT_EXTRACT_ZIP_INVALID_ZIP = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED_INVALID_ZIP';
    public const CANNOT_EXTRACT_ZIP = 'FRAMEWORK__PLUGIN_EXTRACTION_FAILED';
    public const NO_PLUGIN_IN_ZIP = 'FRAMEWORK__PLUGIN_NO_PLUGIN_FOUND_IN_ZIP';
    public const STORE_NOT_AVAILABLE = 'FRAMEWORK__STORE_NOT_AVAILABLE';
    public const CANNOT_CREATE_TEMPORARY_DIRECTORY = 'FRAMEWORK__PLUGIN_CANNOT_CREATE_TEMPORARY_DIRECTORY';
    public const PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';

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

    public static function projectDirNotInContainer(): self
    {
        return new self(
            500,
            self::PROJECT_DIR_IS_NOT_A_STRING,
            'Container parameter "kernel.project_dir" needs to be a string'
        );
    }
}
