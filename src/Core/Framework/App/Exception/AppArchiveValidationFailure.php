<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
class AppArchiveValidationFailure extends AppException
{
    public const APP_EMPTY = 'FRAMEWORK__APP_ARCHIVE_EMPTY';
    public const APP_NO_TOP_LEVEL_FOLDER = 'FRAMEWORK__APP_ARCHIVE_VALIDATION_TOP_LEVEL_FOLDER';
    public const APP_NAME_MISMATCH = 'FRAMEWORK__APP_ARCHIVE_VALIDATION_FAILURE_NAME';
    public const APP_MISSING_MANIFEST = 'FRAMEWORK__APP_ARCHIVE_VALIDATION_FAILURE_MISSING_MANIFEST';
    public const APP_DIRECTORY_TRAVERSAL = 'FRAMEWORK__APP_ARCHIVE_VALIDATION_FAILURE_DIRECTORY_TRAVERSAL';
    public const APP_INVALID_PREFIX = 'FRAMEWORK__APP_ARCHIVE_VALIDATION_FAILURE_INVALID_PREFIX';

    public static function appEmpty(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_EMPTY,
            'App does not contain any files',
        );
    }

    public static function noTopLevelFolder(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_NO_TOP_LEVEL_FOLDER,
            'App zip does not contain any top level folder',
        );
    }

    public static function appNameMismatch(string $expected, string $actual): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_NAME_MISMATCH,
            'App name does not match expected. Expected: "{{ expected }}". Got: "{{ actual }}"',
            ['expected' => $expected, 'actual' => $actual]
        );
    }

    public static function missingManifest(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_MISSING_MANIFEST,
            'App archive does not contain a manifest.xml file',
        );
    }

    public static function directoryTraversal(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_DIRECTORY_TRAVERSAL,
            'Directory traversal detected',
        );
    }

    public static function invalidPrefix(string $filename, string $prefix): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_INVALID_PREFIX,
            'Detected invalid file/directory "{{ filename }}" in the app zip. Expected the directory: "{{ prefix }}"',
            ['filename' => $filename, 'prefix' => $prefix]
        );
    }
}
