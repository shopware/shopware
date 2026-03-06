<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @final
 */
#[Package('discovery')]
readonly class MediaUploadService
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaThumbnailCollection> $thumbnailRepository
     * @param EntityRepository<MediaThumbnailSizeCollection> $thumbnailSizeRepository
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private FileFetcher $fileFetcher,
        private FileSaver $fileSaver,
        private EventDispatcherInterface $eventDispatcher,
        private HttpClientInterface $httpClient,
        private EntityRepository $thumbnailRepository,
        private EntityRepository $thumbnailSizeRepository,
    ) {
    }

    /**
     * Upload a new media file from a local path
     */
    public function uploadFromLocalPath(
        string $filePath,
        Context $context,
        MediaUploadParameters $params = new MediaUploadParameters()
    ): string {
        $size = filesize($filePath);

        if ($size === false) {
            throw MediaException::fileNotFound($filePath);
        }

        $media = new MediaFile(
            $filePath,
            mime_content_type($filePath) ?: '',
            pathinfo($filePath, \PATHINFO_EXTENSION),
            $size,
            Hasher::hashFile($filePath, 'md5'),
        );

        return $this->upload($media, $context, $params);
    }

    /**
     * Upload a new media file provided as form-data in the Request object
     */
    public function uploadFromRequest(
        Request $request,
        Context $context,
        MediaUploadParameters $params = new MediaUploadParameters()
    ): string {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            throw MediaException::fileNotProvided();
        }

        $params->fillDefaultFileName($file->getClientOriginalName());

        $media = new MediaFile(
            $file->getRealPath(),
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
            $file->getSize(),
            Hasher::hashFile($file->getRealPath(), 'md5'),
        );

        return $this->upload($media, $context, $params);
    }

    /**
     * Download the given media file from the URL and upload it as a media file
     */
    public function uploadFromURL(
        string $url,
        Context $context,
        MediaUploadParameters $params = new MediaUploadParameters()
    ): string {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        if (!$tempFile) {
            throw MediaException::cannotCreateTempFile();
        }

        $params->fillDefaultFileName(basename($url));

        try {
            $media = $this->fileFetcher->fetchFromURL($url, $tempFile);

            $id = $this->upload($media, $context, $params);
        } finally {
            unlink($tempFile);
        }

        return $id;
    }

    /**
     * Link the external URL into a new Media object. Shopware does not store any file
     */
    public function linkURL(
        string $url,
        Context $context,
        MediaUploadParameters $params = new MediaUploadParameters()
    ): string {
        $params->fillDefaultFileName(basename($url));

        if ($params->mimeType === null) {
            throw MediaException::mimeTypeNotProvided();
        }

        if ($params->deduplicate) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('path', $url));

            if ($mediaId = $this->mediaRepository->searchIds($criteria, $context)->firstId()) {
                return $mediaId;
            }
        }

        $payload = [
            'id' => $params->id ?? Uuid::randomHex(),
            'userId' => $this->getUserIdFromContext($context),
            'private' => $params->private ?? false,
            'path' => $url,
            'fileSize' => $this->getContentSizeFromValidExternalUrl($url),
            'fileName' => $params->getFileNameWithoutExtension(),
            'fileExtension' => $params->getFileNameExtension(),
            'mimeType' => $params->mimeType,
        ];

        if ($params->mediaFolderId) {
            $payload['mediaFolderId'] = $params->mediaFolderId;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($payload): void {
            $this->mediaRepository->create([$payload], $context);
        });

        if ($params->getThumbnails()->count() > 0) {
            $this->createExternalThumbnails($payload['id'], $params->getThumbnails(), $context);
        }

        return $payload['id'];
    }

    public function addExternalThumbnailsToMedia(string $mediaId, ExternalThumbnailCollection $thumbnails, Context $context): void
    {
        $this->createExternalThumbnails($mediaId, $thumbnails, $context);
    }

    public function deleteAllExternalThumbnails(string $mediaId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));
        $criteria->addFilter(new PrefixFilter('path', 'http'));

        $thumbnailIds = $this->thumbnailRepository->searchIds($criteria, $context)->getIds();

        if ($thumbnailIds === []) {
            return;
        }

        $deletePayload = \array_map(static fn (string $id) => ['id' => $id], $thumbnailIds);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($deletePayload): void {
            $this->thumbnailRepository->delete($deletePayload, $context);
        });
    }

    /**
     * Validate if $url matches the pattern of an external URL. Throws an exception if not.
     */
    public static function validateExternalUrl(string $url): void
    {
        if (!preg_match('/^https?:\/\/.+/', $url)) {
            throw MediaException::invalidUrl($url);
        }
    }

    /**
     * Wrapper around {@see validateExternalUrl()} without throwing an exception
     */
    public static function isExternalUrl(string $url): bool
    {
        try {
            static::validateExternalUrl($url);

            return true;
        } catch (MediaException) {
            return false;
        }
    }

    private function upload(MediaFile $media, Context $context, MediaUploadParameters $params): string
    {
        if ($params->deduplicate && $media->getHash() && $existingId = $this->getMediaIdByHash($media->getHash(), $context)) {
            return $existingId;
        }

        $params->fillDefaultFileName($media->getFileName() . '.' . $media->getFileExtension());

        $changedMediaFile = new MediaFile(
            $media->getFileName(),
            $media->getMimeType(),
            $params->getFileNameExtension(),
            $media->getFileSize(),
            $media->getHash()
        );

        $mediaId = $this->createMedia($params, $context);
        try {
            $this->fileSaver->persistFileToMedia(
                $changedMediaFile,
                $params->getFileNameWithoutExtension(),
                $mediaId,
                $context
            );
        } catch (\Throwable $e) {
            // Delete failed upload item
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId): void {
                $this->mediaRepository->delete([['id' => $mediaId]], $context);
            });

            throw $e;
        }

        $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));

        return $mediaId;
    }

    private function getMediaIdByHash(string $hash, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileHash', $hash));

        return $this->mediaRepository->searchIds($criteria, $context)->firstId();
    }

    private function createMedia(MediaUploadParameters $params, Context $context): string
    {
        $id = $params->id ?? Uuid::randomHex();

        $payload = [
            'id' => $id,
            'private' => $params->private ?? false,
        ];

        if ($params->mediaFolderId) {
            $payload['mediaFolderId'] = $params->mediaFolderId;
        }

        $this->mediaRepository->create([$payload], $context);

        return $id;
    }

    private function getContentSizeFromValidExternalUrl(string $url): int
    {
        $this->validateExternalUrl($url);

        $headers = $this->httpClient->request('HEAD', $url)->getHeaders();
        if (!\array_key_exists('content-length', $headers)) {
            throw MediaException::fileNotFound($url);
        }

        return (int) $headers['content-length'][0];
    }

    private function getUserIdFromContext(Context $context): ?string
    {
        return $context->getSource() instanceof AdminApiSource
            ? $context->getSource()->getUserId()
            : null;
    }

    private function createExternalThumbnails(string $mediaId, ExternalThumbnailCollection $thumbnails, Context $context): void
    {
        $thumbnailPayloads = [];

        foreach ($thumbnails as $thumbnail) {
            $this->validateExternalUrl($thumbnail->url);

            $sizeId = $this->getOrCreateThumbnailSize($thumbnail->width, $thumbnail->height, $context);

            $thumbnailPayloads[] = [
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'path' => $thumbnail->url,
                'width' => $thumbnail->width,
                'height' => $thumbnail->height,
                'mediaThumbnailSizeId' => $sizeId,
            ];
        }

        if ($thumbnailPayloads === []) {
            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($thumbnailPayloads): void {
            $this->thumbnailRepository->create($thumbnailPayloads, $context);
        });
    }

    private function getOrCreateThumbnailSize(int $width, int $height, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('width', $width));
        $criteria->addFilter(new EqualsFilter('height', $height));

        $existingId = $this->thumbnailSizeRepository->searchIds($criteria, $context)->firstId();

        if ($existingId !== null) {
            return $existingId;
        }

        $sizeId = Uuid::randomHex();
        $this->thumbnailSizeRepository->create([[
            'id' => $sizeId,
            'width' => $width,
            'height' => $height,
        ]], $context);

        return $sizeId;
    }
}
