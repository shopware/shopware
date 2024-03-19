<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Content\ImportExport\Exception\FileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileAccessTokenException;
use Shopware\Core\Content\ImportExport\Exception\InvalidIdentifierException;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Exception\ProfileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\RequiredByUserException;
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
}
