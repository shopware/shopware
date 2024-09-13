<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(MediaException::class)]
class MediaExceptionTest extends TestCase
{
    public function testInvalidContentLength(): void
    {
        $exception = MediaException::invalidContentLength();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_INVALID_CONTENT_LENGTH, $exception->getErrorCode());
        static::assertSame('Expected content-length did not match actual size.', $exception->getMessage());
    }

    public function testInvalidUrl(): void
    {
        $url = 'http://invalid-url';

        $exception = MediaException::invalidUrl($url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_INVALID_URL, $exception->getErrorCode());
        static::assertSame('Provided URL "http://invalid-url" is invalid.', $exception->getMessage());
        static::assertSame(['url' => $url], $exception->getParameters());
    }

    public function testIllegalUrl(): void
    {
        $url = 'http://illegal-url';

        $exception = MediaException::illegalUrl($url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_ILLEGAL_URL, $exception->getErrorCode());
        static::assertSame('Provided URL "http://illegal-url" is not allowed.', $exception->getMessage());
        static::assertSame(['url' => $url], $exception->getParameters());
    }

    public function testDisableUrlUploadFeature(): void
    {
        $exception = MediaException::disableUrlUploadFeature();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_DISABLE_URL_UPLOAD_FEATURE, $exception->getErrorCode());
        static::assertSame('The feature to upload a media via URL is disabled.', $exception->getMessage());
    }

    public function testCannotOpenSourceStreamToRead(): void
    {
        $url = 'http://invalid-url';

        $exception = MediaException::cannotOpenSourceStreamToRead($url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_READ, $exception->getErrorCode());
        static::assertSame('Cannot open source stream to read from http://invalid-url.', $exception->getMessage());
        static::assertSame(['url' => $url], $exception->getParameters());
    }

    public function testCannotOpenSourceStreamToWrite(): void
    {
        $fileName = 'invalid-filename';

        $exception = MediaException::cannotOpenSourceStreamToWrite($fileName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_WRITE, $exception->getErrorCode());
        static::assertSame('Cannot open source stream to write upload data: invalid-filename.', $exception->getMessage());
        static::assertSame(['fileName' => $fileName], $exception->getParameters());
    }

    public function testCannotCopyMedia(): void
    {
        $exception = MediaException::cannotCopyMedia();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CANNOT_COPY_MEDIA, $exception->getErrorCode());
        static::assertSame('Error while copying media from source.', $exception->getMessage());
    }

    public function testFileSizeLimitExceeded(): void
    {
        $exception = MediaException::fileSizeLimitExceeded();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_SIZE_LIMIT_EXCEEDED, $exception->getErrorCode());
        static::assertSame('Source file exceeds maximum file size limit.', $exception->getMessage());
    }

    public function testMissingFileExtension(): void
    {
        $exception = MediaException::missingFileExtension();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_MISSING_FILE_EXTENSION, $exception->getErrorCode());
        static::assertSame(
            'No file extension provided. Please use the "extension" query parameter to specify the extension of the uploaded file.',
            $exception->getMessage()
        );
    }

    public function testIllegalFileName(): void
    {
        $fileName = 'illegal-filename';
        $cause = 'cause';

        $exception = MediaException::illegalFileName($fileName, $cause);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_ILLEGAL_FILE_NAME, $exception->getErrorCode());
        static::assertSame('Provided filename "illegal-filename" is not permitted: cause', $exception->getMessage());
        static::assertSame(['fileName' => $fileName, 'cause' => $cause], $exception->getParameters());
    }

    public function testMediaNotFound(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::mediaNotFound($mediaId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find media with id "media-id"', $exception->getMessage());
        static::assertSame($mediaId, $exception->getParameters()['value']);
    }

    public function testInvalidFile(): void
    {
        $cause = 'cause';

        $exception = MediaException::invalidFile($cause);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_INVALID_FILE, $exception->getErrorCode());
        static::assertSame('Provided file is invalid: cause.', $exception->getMessage());
        static::assertSame(['cause' => $cause], $exception->getParameters());
    }

    public function testEmptyMediaFilename(): void
    {
        $exception = MediaException::emptyMediaFilename();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_EMPTY_FILE_NAME, $exception->getErrorCode());
        static::assertSame('A valid filename must be provided.', $exception->getMessage());
    }

    public function testDuplicatedMediaFileName(): void
    {
        $fileName = 'file-name';
        $fileExtension = 'file-extension';

        $exception = MediaException::duplicatedMediaFileName($fileName, $fileExtension);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_DUPLICATED_FILE_NAME, $exception->getErrorCode());
        static::assertSame('A file with the name "file-name.file-extension" already exists.', $exception->getMessage());
        static::assertSame(['fileName' => $fileName, 'fileExtension' => $fileExtension], $exception->getParameters());
    }

    public function testMissingFile(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::missingFile($mediaId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_MISSING_FILE, $exception->getErrorCode());
        static::assertSame('Could not find file for media with id "media-id"', $exception->getMessage());
        static::assertSame($mediaId, $exception->getParameters()['value']);
    }

    public function testMediaFolderIdNotFound(): void
    {
        $folderId = 'folder-id';

        $exception = MediaException::mediaFolderIdNotFound($folderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FOLDER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find media folder with id "folder-id"', $exception->getMessage());
        static::assertSame($folderId, $exception->getParameters()['value']);
    }

    public function testMediaFolderNameNotFound(): void
    {
        $folderName = 'folder-name';

        $exception = MediaException::mediaFolderNameNotFound($folderName);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FOLDER_NAME_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find a folder with name "folder-name"', $exception->getMessage());
        static::assertSame($folderName, $exception->getParameters()['value']);
    }

    public function testDefaultMediaFolderWithEntityNotFound(): void
    {
        $entity = 'product';

        $exception = MediaException::defaultMediaFolderWithEntityNotFound($entity);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_DEFAULT_FOLDER_ENTITY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find a default folder with entity "product"', $exception->getMessage());
        static::assertSame($entity, $exception->getParameters()['value']);
    }

    public function testFileExtensionNotSupported(): void
    {
        $mediaId = 'media-id';
        $extension = 'extension';

        $exception = MediaException::fileExtensionNotSupported($mediaId, $extension);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_TYPE_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('The file extension "extension" for media object with id media-id is not supported.', $exception->getMessage());
        static::assertSame(['mediaId' => $mediaId, 'extension' => $extension], $exception->getParameters());
    }

    public function testCouldNotRenameFile(): void
    {
        $mediaId = 'media-id';
        $oldFileName = 'old-file-name';

        $exception = MediaException::couldNotRenameFile($mediaId, $oldFileName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_COULD_NOT_RENAME_FILE, $exception->getErrorCode());
        static::assertSame('Could not rename file for media with id: media-id. Rollback to filename: "old-file-name"', $exception->getMessage());
        static::assertSame(['mediaId' => $mediaId, 'oldFileName' => $oldFileName], $exception->getParameters());
    }

    public function testEmptyMediaId(): void
    {
        $exception = MediaException::emptyMediaId();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_EMPTY_ID, $exception->getErrorCode());
        static::assertSame('A media id must be provided.', $exception->getMessage());
    }

    public function testInvalidBatchSize(): void
    {
        $exception = MediaException::invalidBatchSize();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_INVALID_BATCH_SIZE, $exception->getErrorCode());
        static::assertSame('Provided batch size is invalid.', $exception->getMessage());
    }

    public function testThumbnailAssociationNotLoaded(): void
    {
        $exception = MediaException::thumbnailAssociationNotLoaded();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_THUMBNAIL_ASSOCIATION_NOT_LOADED, $exception->getErrorCode());
        static::assertSame('Thumbnail association not loaded - please pre load media thumbnails.', $exception->getMessage());
    }

    public function testMediaTypeNotLoaded(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::mediaTypeNotLoaded($mediaId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_TYPE_NOT_LOADED, $exception->getErrorCode());
        static::assertSame('Media type, for id media-id, not loaded', $exception->getMessage());
        static::assertSame(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testThumbnailNotSupported(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::thumbnailNotSupported($mediaId);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL, $exception->getErrorCode());
        static::assertSame('The file for media object with id media-id is not supported for creating thumbnails.', $exception->getMessage());
        static::assertSame(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testThumbnailCouldNotBeSaved(): void
    {
        $url = 'http://url';

        $exception = MediaException::thumbnailCouldNotBeSaved($url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_THUMBNAIL_NOT_SAVED, $exception->getErrorCode());
        static::assertSame('Thumbnail could not be saved to location: http://url.', $exception->getMessage());
        static::assertSame(['location' => $url], $exception->getParameters());
    }

    public function testCannotCreateImage(): void
    {
        $exception = MediaException::cannotCreateImage();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CANNOT_CREATE_IMAGE_HANDLE, $exception->getErrorCode());
        static::assertSame('Can not create image handle.', $exception->getMessage());
    }

    public function testMediaContainsNoThumbnails(): void
    {
        $exception = MediaException::mediaContainsNoThumbnails();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CONTAINS_NO_THUMBNAILS, $exception->getErrorCode());
        static::assertSame('Media contains no thumbnails.', $exception->getMessage());
    }

    public function testStrategyNotFound(): void
    {
        $strategyName = 'strategy-name';

        $exception = MediaException::strategyNotFound($strategyName);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_STRATEGY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No Strategy with name "strategy-name" found.', $exception->getMessage());
        static::assertSame(['strategyName' => $strategyName], $exception->getParameters());
    }

    public function testInvalidFilesystemVisibility(): void
    {
        $exception = MediaException::invalidFilesystemVisibility();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_INVALID_FILE_SYSTEM_VISIBILITY, $exception->getErrorCode());
        static::assertSame('Invalid filesystem visibility.', $exception->getMessage());
    }

    public function testFileIsNotInstanceOfFileSystem(): void
    {
        $exception = MediaException::fileIsNotInstanceOfFileSystem();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_IS_NOT_INSTANCE_OF_FILE_SYSTEM, $exception->getErrorCode());
        static::assertSame('File is not an instance of FileSystem', $exception->getMessage());
    }

    public function testMissingUrlParameter(): void
    {
        $exception = MediaException::missingUrlParameter();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_MISSING_URL_PARAMETER, $exception->getErrorCode());
        static::assertSame('Parameter url is missing.', $exception->getMessage());
    }

    public function testCannotCreateTempFile(): void
    {
        $exception = MediaException::cannotCreateTempFile();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_CANNOT_CREATE_TEMP_FILE, $exception->getErrorCode());
        static::assertSame('Cannot create a temp file.', $exception->getMessage());
    }

    public function testFileNotFound(): void
    {
        $path = 'file-name';
        $exception = MediaException::fileNotFound($path);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('The file "file-name" does not exist', $exception->getMessage());
        static::assertSame(['path' => $path], $exception->getParameters());
    }

    public function testFileNameTooLong(): void
    {
        $exception = MediaException::fileNameTooLong(3);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_FILE_NAME_IS_TOO_LONG, $exception->getErrorCode());
        static::assertSame('The provided file name is too long, the maximum length is 3 characters.', $exception->getMessage());
        static::assertSame(['maxLength' => 3], $exception->getParameters());
    }

    public function testThumbnailGenerationDisabled(): void
    {
        $exception = MediaException::thumbnailGenerationDisabled();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MediaException::MEDIA_THUMBNAIL_GENERATION_DISABLED, $exception->getErrorCode());
        static::assertSame('Remote thumbnails are enabled. Skipping thumbnail generation.', $exception->getMessage());
    }
}
