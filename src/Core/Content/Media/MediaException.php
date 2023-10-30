<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Exception\CouldNotRenameFileException;
use Shopware\Core\Content\Media\Exception\DisabledUrlUploadFeatureException;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;
use Shopware\Core\Content\Media\Exception\FileExtensionNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\Exception\IllegalUrlException;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\MissingFileException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\StrategyNotFoundException;
use Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException;
use Shopware\Core\Content\Media\Exception\ThumbnailNotSupportedException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class MediaException extends HttpException
{
    public const MEDIA_INVALID_CONTENT_LENGTH = 'CONTENT__MEDIA_INVALID_CONTENT_LENGTH';
    public const MEDIA_INVALID_URL = 'CONTENT__MEDIA_INVALID_URL';
    public const MEDIA_INVALID_URL_GENERATOR_PARAMETER = 'CONTENT__MEDIA_INVALID_URL_GENERATOR_PARAMETER';
    public const MEDIA_ILLEGAL_URL = 'CONTENT__MEDIA_ILLEGAL_URL';
    public const MEDIA_DISABLE_URL_UPLOAD_FEATURE = 'CONTENT__MEDIA_DISABLE_URL_UPLOAD_FEATURE';
    public const MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_READ = 'CONTENT__MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_READ';
    public const MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_WRITE = 'CONTENT__MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_WRITE';
    public const MEDIA_CANNOT_COPY_MEDIA = 'CONTENT__MEDIA_CANNOT_COPY_MEDIA';
    public const MEDIA_FILE_SIZE_LIMIT_EXCEEDED = 'CONTENT__MEDIA_FILE_SIZE_LIMIT_EXCEEDED';
    public const MEDIA_MISSING_FILE_EXTENSION = 'CONTENT__MEDIA_MISSING_FILE_EXTENSION';
    public const MEDIA_ILLEGAL_FILE_NAME = 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';
    public const MEDIA_EMPTY_FILE = 'CONTENT__MEDIA_EMPTY_FILE';
    public const MEDIA_INVALID_FILE = 'CONTENT__MEDIA_INVALID_FILE';
    public const MEDIA_EMPTY_FILE_NAME = 'CONTENT__MEDIA_EMPTY_FILE_NAME';
    public const MEDIA_FOLDER_NOT_FOUND = 'CONTENT__MEDIA_FOLDER_NOT_FOUND';
    public const MEDIA_FOLDER_NAME_NOT_FOUND = 'CONTENT__MEDIA_FOLDER_NAME_NOT_FOUND';
    public const MEDIA_FILE_TYPE_NOT_SUPPORTED = 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    public const MEDIA_COULD_NOT_RENAME_FILE = 'CONTENT__MEDIA_COULD_NOT_RENAME_FILE';
    public const MEDIA_EMPTY_ID = 'CONTENT__MEDIA_EMPTY_ID';
    public const MEDIA_INVALID_BATCH_SIZE = 'CONTENT__MEDIA_INVALID_BATCH_SIZE';
    public const MEDIA_THUMBNAIL_ASSOCIATION_NOT_LOADED = 'CONTENT__MEDIA_THUMBNAIL_ASSOCIATION_NOT_LOADED';
    public const MEDIA_TYPE_NOT_LOADED = 'CONTENT__MEDIA_TYPE_NOT_LOADED';
    public const MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL = 'CONTENT__MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL';
    public const MEDIA_THUMBNAIL_NOT_SAVED = 'CONTENT__MEDIA_THUMBNAIL_NOT_SAVED';
    public const MEDIA_CANNOT_CREATE_IMAGE_HANDLE = 'CONTENT__MEDIA_CANNOT_CREATE_IMAGE_HANDLE';
    public const MEDIA_CONTAINS_NO_THUMBNAILS = 'CONTENT__MEDIA_CONTAINS_NO_THUMBNAILS';
    public const MEDIA_STRATEGY_NOT_FOUND = 'CONTENT__MEDIA_STRATEGY_NOT_FOUND';
    public const MEDIA_INVALID_FILE_SYSTEM_VISIBILITY = 'CONTENT__MEDIA_INVALID_FILE_SYSTEM_VISIBILITY';
    public const MEDIA_FILE_IS_NOT_INSTANCE_OF_FILE_SYSTEM = 'CONTENT__MEDIA_FILE_IS_NOT_INSTANCE_OF_FILE_SYSTEM';
    public const MEDIA_MISSING_URL_PARAMETER = 'CONTENT__MEDIA_MISSING_URL_PARAMETER';
    public const MEDIA_CANNOT_CREATE_TEMP_FILE = 'CONTENT__MEDIA_CANNOT_CREATE_TEMP_FILE';
    public const MEDIA_FILE_NOT_FOUND = 'CONTENT__MEDIA_FILE_NOT_FOUND';
    public const MEDIA_MISSING_FILE = 'CONTENT__MEDIA_MISSING_FILE';
    public const MEDIA_NOT_FOUND = 'CONTENT__MEDIA_NOT_FOUND';
    public const MEDIA_DUPLICATED_FILE_NAME = 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';

    public static function invalidContentLength(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException('Expected content-length did not match actual size.');
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_INVALID_CONTENT_LENGTH,
            'Expected content-length did not match actual size.'
        );
    }

    public static function invalidUrl(string $url): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException(sprintf('Provided URL "%s" is invalid.', $url));
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_INVALID_URL,
            'Provided URL "{{ url }}" is invalid.',
            ['url' => $url]
        );
    }

    public static function illegalUrl(string $url): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new IllegalUrlException($url);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_ILLEGAL_URL,
            'Provided URL "{{ url }}" is not allowed.',
            ['url' => $url]
        );
    }

    public static function disableUrlUploadFeature(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new DisabledUrlUploadFeatureException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_DISABLE_URL_UPLOAD_FEATURE,
            'The feature to upload a media via URL is disabled.'
        );
    }

    public static function cannotOpenSourceStreamToRead(string $url): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException(sprintf('Cannot open source stream to read from %s.', $url));
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_READ,
            'Cannot open source stream to read from {{ url }}.',
            ['url' => $url]
        );
    }

    public static function cannotOpenSourceStreamToWrite(string $fileName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException(sprintf('Cannot open source stream to write upload data: %s.', $fileName));
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_WRITE,
            'Cannot open source stream to write upload data: {{ fileName }}.',
            ['fileName' => $fileName]
        );
    }

    public static function cannotCopyMedia(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException('Error while copying media from source.');
        }

        return new self(
            Response::HTTP_CONFLICT,
            self::MEDIA_CANNOT_COPY_MEDIA,
            'Error while copying media from source.'
        );
    }

    public static function fileSizeLimitExceeded(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException('Source file exceeds maximum file size limit.');
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_FILE_SIZE_LIMIT_EXCEEDED,
            'Source file exceeds maximum file size limit.'
        );
    }

    public static function missingFileExtension(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new MissingFileExtensionException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_MISSING_FILE_EXTENSION,
            'No file extension provided. Please use the "extension" query parameter to specify the extension of the uploaded file.'
        );
    }

    public static function illegalFileName(string $filename, string $cause): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new IllegalFileNameException($filename, $cause);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_ILLEGAL_FILE_NAME,
            'Provided filename "{{ fileName }}" is not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }

    public static function mediaNotFound(string $mediaId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new MediaNotFoundException($mediaId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'media', 'field' => 'id', 'value' => $mediaId]
        );
    }

    public static function invalidFile(string $cause): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException(sprintf('Provided file is invalid: %s.', $cause));
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_INVALID_FILE,
            'Provided file is invalid: {{ cause }}.',
            ['cause' => $cause]
        );
    }

    public static function emptyMediaFilename(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new EmptyMediaFilenameException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_EMPTY_FILE_NAME,
            'A valid filename must be provided.'
        );
    }

    public static function duplicatedMediaFileName(string $fileName, string $fileExtension): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new DuplicatedMediaFileNameException($fileName, $fileExtension);
        }

        return new self(
            Response::HTTP_CONFLICT,
            self::MEDIA_DUPLICATED_FILE_NAME,
            'A file with the name "{{ fileName }}.{{ fileExtension }}" already exists.',
            ['fileName' => $fileName, 'fileExtension' => $fileExtension]
        );
    }

    public static function missingFile(string $mediaId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new MissingFileException($mediaId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_MISSING_FILE,
            self::$couldNotFindMessage,
            ['entity' => 'file for media', 'field' => 'id', 'value' => $mediaId]
        );
    }

    public static function mediaFolderIdNotFound(string $folderId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new MediaFolderNotFoundException($folderId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_FOLDER_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'media folder', 'field' => 'id', 'value' => $folderId]
        );
    }

    public static function mediaFolderNameNotFound(string $folderName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_FOLDER_NAME_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'a folder', 'field' => 'name', 'value' => $folderName]
        );
    }

    public static function fileExtensionNotSupported(string $mediaId, string $extension): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new FileExtensionNotSupportedException($mediaId, $extension);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_FILE_TYPE_NOT_SUPPORTED,
            'The file extension "{{ extension }}" for media object with id {{ mediaId }} is not supported.',
            ['mediaId' => $mediaId, 'extension' => $extension]
        );
    }

    public static function couldNotRenameFile(string $mediaId, string $oldFileName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new CouldNotRenameFileException($mediaId, $oldFileName);
        }

        return new self(
            Response::HTTP_CONFLICT,
            self::MEDIA_COULD_NOT_RENAME_FILE,
            'Could not rename file for media with id: {{ mediaId }}. Rollback to filename: "{{ oldFileName }}"',
            ['mediaId' => $mediaId, 'oldFileName' => $oldFileName]
        );
    }

    public static function emptyMediaId(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new EmptyMediaIdException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_EMPTY_ID,
            'A media id must be provided.'
        );
    }

    public static function invalidBatchSize(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_INVALID_BATCH_SIZE,
            'Provided batch size is invalid.'
        );
    }

    public static function thumbnailAssociationNotLoaded(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_THUMBNAIL_ASSOCIATION_NOT_LOADED,
            'Thumbnail association not loaded - please pre load media thumbnails.'
        );
    }

    public static function mediaTypeNotLoaded(string $mediaId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_TYPE_NOT_LOADED,
            'Media type, for id {{ mediaId }}, not loaded',
            ['mediaId' => $mediaId]
        );
    }

    public static function thumbnailNotSupported(string $mediaId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new ThumbnailNotSupportedException($mediaId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL,
            'The file for media object with id {{ mediaId }} is not supported for creating thumbnails.',
            ['mediaId' => $mediaId]
        );
    }

    public static function thumbnailCouldNotBeSaved(string $url): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new ThumbnailCouldNotBeSavedException($url);
        }

        return new self(
            Response::HTTP_CONFLICT,
            self::MEDIA_THUMBNAIL_NOT_SAVED,
            'Thumbnail could not be saved to location: {{ location }}.',
            ['location' => $url]
        );
    }

    public static function cannotCreateImage(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_CANNOT_CREATE_IMAGE_HANDLE,
            'Can not create image handle.'
        );
    }

    public static function mediaContainsNoThumbnails(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_CONTAINS_NO_THUMBNAILS,
            'Media contains no thumbnails.'
        );
    }

    public static function strategyNotFound(string $strategyName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StrategyNotFoundException($strategyName);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_STRATEGY_NOT_FOUND,
            'No Strategy with name "{{ strategyName }}" found.',
            ['strategyName' => $strategyName]
        );
    }

    public static function invalidFilesystemVisibility(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_INVALID_FILE_SYSTEM_VISIBILITY,
            'Invalid filesystem visibility.'
        );
    }

    public static function fileIsNotInstanceOfFileSystem(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_FILE_IS_NOT_INSTANCE_OF_FILE_SYSTEM,
            'File is not an instance of FileSystem'
        );
    }

    public static function missingUrlParameter(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new UploadException('Parameter url is missing.');
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_MISSING_URL_PARAMETER,
            'Parameter url is missing.'
        );
    }

    public static function cannotCreateTempFile(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_CANNOT_CREATE_TEMP_FILE,
            'Cannot create a temp file.'
        );
    }

    public static function fileNotFound(string $path): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEDIA_FILE_NOT_FOUND,
            'The file "{{ path }}" does not exist',
            ['path' => $path]
        );
    }

    public static function invalidUrlGeneratorParameter(string|int $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_INVALID_URL,
            'The url generator parameter "{{ key }}" is invalid.',
            ['key' => $key]
        );
    }
}
