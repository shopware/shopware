<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\MediaException
 */
#[Package('buyers-experience')]
class MediaExceptionTest extends TestCase
{
    public function testInvalidContentLength(): void
    {
        $exception = MediaException::invalidContentLength();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_INVALID_CONTENT_LENGTH, $exception->getErrorCode());
        static::assertEquals('Expected content-length did not match actual size.', $exception->getMessage());
    }

    public function testInvalidUrl(): void
    {
        $url = 'http://invalid-url';

        $exception = MediaException::invalidUrl($url);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_INVALID_URL, $exception->getErrorCode());
        static::assertEquals('Provided URL "http://invalid-url" is invalid.', $exception->getMessage());
        static::assertEquals(['url' => $url], $exception->getParameters());
    }

    public function testIllegalUrl(): void
    {
        $url = 'http://illegal-url';

        $exception = MediaException::illegalUrl($url);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_ILLEGAL_URL, $exception->getErrorCode());
        static::assertEquals('Provided URL "http://illegal-url" is not allowed.', $exception->getMessage());
        static::assertEquals(['url' => $url], $exception->getParameters());
    }

    public function testDisableUrlUploadFeature(): void
    {
        $exception = MediaException::disableUrlUploadFeature();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_DISABLE_URL_UPLOAD_FEATURE, $exception->getErrorCode());
        static::assertEquals('The feature to upload a media via URL is disabled.', $exception->getMessage());
    }

    public function testCannotOpenSourceStreamToRead(): void
    {
        $url = 'http://invalid-url';

        $exception = MediaException::cannotOpenSourceStreamToRead($url);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_READ, $exception->getErrorCode());
        static::assertEquals('Cannot open source stream to read from http://invalid-url.', $exception->getMessage());
        static::assertEquals(['url' => $url], $exception->getParameters());
    }

    public function testCannotOpenSourceStreamToWrite(): void
    {
        $fileName = 'invalid-filename';

        $exception = MediaException::cannotOpenSourceStreamToWrite($fileName);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CANNOT_OPEN_SOURCE_STREAM_TO_WRITE, $exception->getErrorCode());
        static::assertEquals('Cannot open source stream to write upload data: invalid-filename.', $exception->getMessage());
        static::assertEquals(['fileName' => $fileName], $exception->getParameters());
    }

    public function testCannotCopyMedia(): void
    {
        $exception = MediaException::cannotCopyMedia();

        static::assertEquals(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CANNOT_COPY_MEDIA, $exception->getErrorCode());
        static::assertEquals('Error while copying media from source.', $exception->getMessage());
    }

    public function testFileSizeLimitExceeded(): void
    {
        $exception = MediaException::fileSizeLimitExceeded();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FILE_SIZE_LIMIT_EXCEEDED, $exception->getErrorCode());
        static::assertEquals('Source file exceeds maximum file size limit.', $exception->getMessage());
    }

    public function testMissingFileExtension(): void
    {
        $exception = MediaException::missingFileExtension();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_MISSING_FILE_EXTENSION, $exception->getErrorCode());
        static::assertEquals(
            'No file extension provided. Please use the "extension" query parameter to specify the extension of the uploaded file.',
            $exception->getMessage()
        );
    }

    public function testIllegalFileName(): void
    {
        $fileName = 'illegal-filename';
        $cause = 'cause';

        $exception = MediaException::illegalFileName($fileName, $cause);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_ILLEGAL_FILE_NAME, $exception->getErrorCode());
        static::assertEquals('Provided filename "illegal-filename" is not permitted: cause', $exception->getMessage());
        static::assertEquals(['fileName' => $fileName, 'cause' => $cause], $exception->getParameters());
    }

    public function testMediaNotFound(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::mediaNotFound($mediaId);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Media for id media-id not found.', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testInvalidFile(): void
    {
        $cause = 'cause';

        $exception = MediaException::invalidFile($cause);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_INVALID_FILE, $exception->getErrorCode());
        static::assertEquals('Provided file is invalid: cause.', $exception->getMessage());
        static::assertEquals(['cause' => $cause], $exception->getParameters());
    }

    public function testEmptyMediaFilename(): void
    {
        $exception = MediaException::emptyMediaFilename();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_EMPTY_FILE_NAME, $exception->getErrorCode());
        static::assertEquals('A valid filename must be provided.', $exception->getMessage());
    }

    public function testDuplicatedMediaFileName(): void
    {
        $fileName = 'file-name';
        $fileExtension = 'file-extension';

        $exception = MediaException::duplicatedMediaFileName($fileName, $fileExtension);

        static::assertEquals(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_DUPLICATED_FILE_NAME, $exception->getErrorCode());
        static::assertEquals('A file with the name "file-name.file-extension" already exists.', $exception->getMessage());
        static::assertEquals(['fileName' => $fileName, 'fileExtension' => $fileExtension], $exception->getParameters());
    }

    public function testMissingFile(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::missingFile($mediaId);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_MISSING_FILE, $exception->getErrorCode());
        static::assertEquals('Could not find file for media with id: "media-id"', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testMediaFolderIdNotFound(): void
    {
        $folderId = 'folder-id';

        $exception = MediaException::mediaFolderIdNotFound($folderId);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FOLDER_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Could not find media folder with id: "folder-id"', $exception->getMessage());
        static::assertEquals(['folderId' => $folderId], $exception->getParameters());
    }

    public function testMediaFolderNameNotFound(): void
    {
        $folderName = 'folder-name';

        $exception = MediaException::mediaFolderNameNotFound($folderName);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FOLDER_NAME_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Could not find a folder with the name: "folder-name"', $exception->getMessage());
        static::assertEquals(['folderName' => $folderName], $exception->getParameters());
    }

    public function testFileExtensionNotSupported(): void
    {
        $mediaId = 'media-id';
        $extension = 'extension';

        $exception = MediaException::fileExtensionNotSupported($mediaId, $extension);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FILE_TYPE_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertEquals('The file extension "extension" for media object with id media-id is not supported.', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId, 'extension' => $extension], $exception->getParameters());
    }

    public function testCouldNotRenameFile(): void
    {
        $mediaId = 'media-id';
        $oldFileName = 'old-file-name';

        $exception = MediaException::couldNotRenameFile($mediaId, $oldFileName);

        static::assertEquals(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_COULD_NOT_RENAME_FILE, $exception->getErrorCode());
        static::assertEquals('Could not rename file for media with id: media-id. Rollback to filename: "old-file-name"', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId, 'oldFileName' => $oldFileName], $exception->getParameters());
    }

    public function testEmptyMediaId(): void
    {
        $exception = MediaException::emptyMediaId();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_EMPTY_ID, $exception->getErrorCode());
        static::assertEquals('A media id must be provided.', $exception->getMessage());
    }

    public function testInvalidBatchSize(): void
    {
        $exception = MediaException::invalidBatchSize();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_INVALID_BATCH_SIZE, $exception->getErrorCode());
        static::assertEquals('Provided batch size is invalid.', $exception->getMessage());
    }

    public function testThumbnailAssociationNotLoaded(): void
    {
        $exception = MediaException::thumbnailAssociationNotLoaded();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_THUMBNAIL_ASSOCIATION_NOT_LOADED, $exception->getErrorCode());
        static::assertEquals('Thumbnail association not loaded - please pre load media thumbnails.', $exception->getMessage());
    }

    public function testMediaTypeNotLoaded(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::mediaTypeNotLoaded($mediaId);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_TYPE_NOT_LOADED, $exception->getErrorCode());
        static::assertEquals('Media type, for id media-id, not loaded', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testThumbnailNotSupported(): void
    {
        $mediaId = 'media-id';

        $exception = MediaException::thumbnailNotSupported($mediaId);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL, $exception->getErrorCode());
        static::assertEquals('The file for media object with id media-id is not supported for creating thumbnails.', $exception->getMessage());
        static::assertEquals(['mediaId' => $mediaId], $exception->getParameters());
    }

    public function testThumbnailCouldNotBeSaved(): void
    {
        $url = 'http://url';

        $exception = MediaException::thumbnailCouldNotBeSaved($url);

        static::assertEquals(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_THUMBNAIL_NOT_SAVED, $exception->getErrorCode());
        static::assertEquals('Thumbnail could not be saved to location: http://url.', $exception->getMessage());
        static::assertEquals(['location' => $url], $exception->getParameters());
    }

    public function testCannotCreateImage(): void
    {
        $exception = MediaException::cannotCreateImage();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CANNOT_CREATE_IMAGE_HANDLE, $exception->getErrorCode());
        static::assertEquals('Can not create image handle.', $exception->getMessage());
    }

    public function testMediaContainsNoThumbnails(): void
    {
        $exception = MediaException::mediaContainsNoThumbnails();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CONTAINS_NO_THUMBNAILS, $exception->getErrorCode());
        static::assertEquals('Media contains no thumbnails.', $exception->getMessage());
    }

    public function testStrategyNotFound(): void
    {
        $strategyName = 'strategy-name';

        $exception = MediaException::strategyNotFound($strategyName);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_STRATEGY_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('No Strategy with name "strategy-name" found.', $exception->getMessage());
        static::assertEquals(['strategyName' => $strategyName], $exception->getParameters());
    }

    public function testInvalidFilesystemVisibility(): void
    {
        $exception = MediaException::invalidFilesystemVisibility();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_INVALID_FILE_SYSTEM_VISIBILITY, $exception->getErrorCode());
        static::assertEquals('Invalid filesystem visibility.', $exception->getMessage());
    }

    public function testFileIsNotInstanceOfFileSystem(): void
    {
        $exception = MediaException::fileIsNotInstanceOfFileSystem();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FILE_IS_NOT_INSTANCE_OF_FILE_SYSTEM, $exception->getErrorCode());
        static::assertEquals('File is not an instance of FileSystem', $exception->getMessage());
    }

    public function testMissingUrlParameter(): void
    {
        $exception = MediaException::missingUrlParameter();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_MISSING_URL_PARAMETER, $exception->getErrorCode());
        static::assertEquals('Parameter url is missing.', $exception->getMessage());
    }

    public function testCannotCreateTempFile(): void
    {
        $exception = MediaException::cannotCreateTempFile();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_CANNOT_CREATE_TEMP_FILE, $exception->getErrorCode());
        static::assertEquals('Cannot create a temp file.', $exception->getMessage());
    }

    public function testFileNotFound(): void
    {
        $path = 'file-name';
        $exception = MediaException::fileNotFound($path);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(MediaException::MEDIA_FILE_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('The file "file-name" does not exist', $exception->getMessage());
        static::assertEquals(['path' => $path], $exception->getParameters());
    }
}
