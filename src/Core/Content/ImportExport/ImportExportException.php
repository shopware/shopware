<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Exception\FileEmptyException;
use Shopware\Core\Content\ImportExport\Exception\FileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileAccessTokenException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileContentException;
use Shopware\Core\Content\ImportExport\Exception\InvalidIdentifierException;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\ProfileWrongTypeException;
use Shopware\Core\Content\ImportExport\Exception\RequiredByUserException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class ImportExportException extends HttpException
{
    final public const CONTENT_IMPORT_EXPORT_COULD_NOT_OPEN_FILE = 'CONTENT__IMPORT_EXPORT__COULD_NOT_OPEN_FILE';
    final public const CONTENT_IMPORT_EXPORT_COULD_NOT_CREATE_FILE = 'CONTENT__IMPORT_EXPORT__COULD_NOT_CREATE_FILE';
    final public const CONTENT_IMPORT_EXPORT_COULD_NOT_COPY_FILE = 'CONTENT__IMPORT_EXPORT__COULD_NOT_COPY_FILE';
    final public const CONTENT_IMPORT_EXPORT_COULD_NOT_WRITE_TO_BUFFER = 'CONTENT__IMPORT_EXPORT__COULD_NOT_WRITE_TO_BUFFER';
    public const FIELD_CANNOT_BE_EXPORTED = 'IMPORT_EXPORT__FIELD_CANNOT_BE_EXPORTED';
    public const FILE_EMPTY = 'CONTENT__IMPORT_EXPORT_FILE_EMPTY';
    public const FILE_NOT_READABLE = 'CONTENT__IMPORT_FILE_IS_NOT_READABLE';
    public const INVALID_FILE_CONTENT = 'CONTENT__IMPORT_EXPORT_INVALID_FILE_CONTENT';
    public const LOG_ENTITY_NOT_FOUND = 'CONTENT__IMPORT_EXPORT__LOG_ENTITY_NOT_FOUND';
    public const PROFILE_WITHOUT_MAPPINGS = 'CONTENT__IMPORT_EXPORT_PROFILE_WITHOUT_MAPPINGS';
    public const PROFILE_WRONG_TYPE = 'CONTENT__IMPORT_EXPORT_PROFILE_WRONG_TYPE';
    public const UNEXPECTED_FILE_TYPE = 'CONTENT__IMPORT_FILE_HAS_UNEXPECTED_TYPE';
    public const UNKNOWN_ACTIVITY = 'CONTENT__IMPORT_EXPORT__UNKNOWN_ACTIVITY';
    public const FILE_PATH_NOT_FOUND = 'CONTENT__IMPORT_EXPORT__FILE_PATH_NOT_FOUND';

    public static function invalidFileAccessToken(): ShopwareHttpException
    {
        return new InvalidFileAccessTokenException();
    }

    public static function fileNotFound(string $fileId): ShopwareHttpException
    {
        return new FileNotFoundException($fileId);
    }

    public static function processingError(string $message): ShopwareHttpException
    {
        return new ProcessingException($message);
    }

    public static function requiredByUser(string $column): ShopwareHttpException
    {
        return new RequiredByUserException($column);
    }

    public static function invalidIdentifier(string $id): ShopwareHttpException
    {
        return new InvalidIdentifierException($id);
    }

    public static function decorationPattern(string $class): ShopwareHttpException
    {
        return new DecorationPatternException($class);
    }

    public static function profileNotFound(string $profileId): ShopwareHttpException
    {
        return new ProfileNotFoundException($profileId);
    }

    public static function couldNotOpenFile(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTENT_IMPORT_EXPORT_COULD_NOT_OPEN_FILE,
            'Could not open file at: {{ path }}',
            ['path' => $path]
        );
    }

    public static function couldNotCreateFile(string $directoryPath): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTENT_IMPORT_EXPORT_COULD_NOT_CREATE_FILE,
            'Could not create file in directory: {{ directoryPath }}',
            ['directoryPath' => $directoryPath]
        );
    }

    public static function couldNotCopyFile(string $toPath): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTENT_IMPORT_EXPORT_COULD_NOT_COPY_FILE,
            'Could not copy file from buffer to "{{ toPath }}"',
            ['toPath' => $toPath]
        );
    }

    public static function couldNotWriteToBuffer(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTENT_IMPORT_EXPORT_COULD_NOT_WRITE_TO_BUFFER,
            'Could not write to buffer'
        );
    }

    public static function fieldCannotBeExported(string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FIELD_CANNOT_BE_EXPORTED,
            'Field of type {{ class }} cannot be exported.',
            ['class' => $class]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function fileEmpty(string $filename): self|ShopwareHttpException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new FileEmptyException($filename);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FILE_EMPTY,
            'The file {{ filename }} is empty.',
            ['filename' => $filename]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function fileNotReadable(string $path): self|ShopwareHttpException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new FileNotReadableException($path);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FILE_NOT_READABLE,
            'Import file is not readable at {{ path }}.',
            ['path' => $path]
        );
    }

    public static function filePathNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FILE_PATH_NOT_FOUND,
            'File path does not exist.'
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function invalidFileContent(string $filename): ShopwareHttpException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidFileContentException($filename);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FILE_CONTENT,
            'The content of the file {{ filename }} is invalid.',
            ['filename' => $filename]
        );
    }

    public static function logEntityNotFound(string $logId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOG_ENTITY_NOT_FOUND,
            'Import/Export log "{{ logId }}" not found.',
            ['logId' => $logId]
        );
    }

    public static function profileWithoutMappings(string $profileId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROFILE_WITHOUT_MAPPINGS,
            'Import/Export profile "{{ profileId }}" has no mappings.',
            ['profileId' => $profileId]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function profileWrongType(string $profileId, string $profileType): self|ShopwareHttpException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new ProfileWrongTypeException($profileId, $profileType);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::PROFILE_WRONG_TYPE,
            'The import/export profile with id {{ profileId }} can only be used for {{ profileType }}',
            ['profileId' => $profileId, 'profileType' => $profileType]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function unexpectedFileType(string $givenType, string $expectedType): self|ShopwareHttpException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new UnexpectedFileTypeException($givenType, $expectedType);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNEXPECTED_FILE_TYPE,
            'Given file does not match MIME-Type for selected profile. Given: {{ given }}. Expected: {{ expected }}',
            ['given' => $givenType, 'expected' => $expectedType]
        );
    }

    public static function unknownActivity(string $activity): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_ACTIVITY,
            'The activity "{{ activity }}" could not be processed.',
            ['activity' => $activity]
        );
    }
}
