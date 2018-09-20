<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;

class FileSaver
{
    /**
     * @var RepositoryInterface
     */
    protected $mediaRepository;

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var MetadataLoader
     */
    private $metadataLoader;

    public function __construct(
        RepositoryInterface $mediaRepository,
        FilesystemInterface $filesystem,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailService $thumbnailService,
        MetadataLoader $metadataLoader
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailService = $thumbnailService;
        $this->metadataLoader = $metadataLoader;
    }

    /**
     * @throws MediaNotFoundException
     */
    public function persistFileToMedia(MediaFile $mediaFile, string $mediaId, Context $context): void
    {
        // @todo remove with NEXT-817
        $currentMedia = $this->getCurrentMedia($mediaId, $context);

        $this->removeOldMediaData($currentMedia, $context);
        $rawMetadata = $this->metadataLoader->loadFromFile($mediaFile);

        $media = $this->updateMediaEntity($mediaFile, $mediaId, $rawMetadata, $context);
        $this->saveFileToMediaDir($mediaFile, $media);

        try {
            $this->thumbnailService->updateThumbnailsAfterUpload($media, $context);
        } catch (FileTypeNotSupportedException $e) {
            //ignore wrong filetype
        }
    }

    private function getCurrentMedia(string $mediaId, Context $context): MediaStruct
    {
        $mediaCollection = $this->mediaRepository->read(new ReadCriteria([$mediaId]), $context);
        $currentMedia = $mediaCollection->get($mediaId);

        if ($currentMedia === null) {
            throw new MediaNotFoundException($mediaId);
        }

        return $currentMedia;
    }

    private function removeOldMediaData(MediaStruct $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $this->filesystem->delete($oldMediaFilePath);

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, MediaStruct $media): void
    {
        $stream = fopen($mediaFile->getFileName(), 'r');
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
        try {
            $this->filesystem->putStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function updateMediaEntity(
        MediaFile $mediaFile,
        string $mediaId,
        Metadata $metadata,
        Context $context
    ): MediaStruct {
        $data = [
            'id' => $mediaId,
            'mimeType' => $mediaFile->getMimeType(),
            'fileExtension' => $mediaFile->getFileExtension(),
            'fileSize' => $mediaFile->getFileSize(),
            'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
            'metaData' => $metadata,
        ];

        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->mediaRepository->update([$data], $context);

        return $this->mediaRepository->read(new ReadCriteria([$mediaId]), $context)->get($mediaId);
    }
}
