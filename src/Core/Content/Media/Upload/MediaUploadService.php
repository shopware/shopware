<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private FileFetcher $fileFetcher,
        private FileSaver $fileSaver,
        private EventDispatcherInterface $eventDispatcher,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Upload a new media file from a local path
     */
    public function uploadFromLocalPath(string $filePath, Context $context, MediaUploadParameters $params = new MediaUploadParameters()): string
    {
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
    public function uploadFromRequest(Request $request, Context $context, MediaUploadParameters $params = new MediaUploadParameters()): string
    {
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
     * Download the given media file from the URL and upload it as own file
     */
    public function uploadFromURL(string $url, Context $context, MediaUploadParameters $params = new MediaUploadParameters()): string
    {
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
    public function linkURL(string $url, Context $context, MediaUploadParameters $params = new MediaUploadParameters()): string
    {
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

        $headers = $this->httpClient->request('HEAD', $url)->getHeaders();

        if (!isset($headers['content-length'])) {
            throw MediaException::fileNotFound($url);
        }

        $payload = [
            'id' => $params->id ?? Uuid::randomHex(),
            'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            'private' => $params->private ?? false,
            'path' => $url,
            'fileSize' => (int) $headers['content-length'][0],
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

        return $payload['id'];
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
}
