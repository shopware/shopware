<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;

class FileSaver
{
    /**
     * @var RepositoryInterface
     */
    protected $repository;

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
        RepositoryInterface $repository,
        FilesystemInterface $filesystem,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailService $thumbnailService,
        MetadataLoader $metadataLoader
    ) {
        $this->repository = $repository;
        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailService = $thumbnailService;
        $this->metadataLoader = $metadataLoader;
    }

    /**
     * @throws IllegalMimeTypeException
     * @throws UploadException
     */
    public function persistFileToMedia(MediaFile $mediaFile, string $mediaId, Context $context): void
    {
        // @todo remove with NEXT-817
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('id', $mediaId));

        $searchResult = $this->repository->search($criteria, $context);
        if (($currentMedia = $searchResult->getEntities()->get($mediaId)) === null) {
            throw new MediaNotFoundException($mediaId);
        }

        $this->removeOldMediaData($currentMedia, $mediaFile, $context);
        $rawMetadata = $this->metadataLoader->loadFromFile($mediaFile);

        $this->saveFileToMediaDir($mediaFile, $mediaId);
        $media = $this->updateMediaEntity($mediaFile, $mediaId, $rawMetadata, $context);

        try {
            $this->thumbnailService->updateThumbnailsAfterUpload($media, $context);
        } catch (FileTypeNotSupportedException $e) {
            //ignore wrong filetype
        }
    }

    private function removeOldMediaData(MediaStruct $media, MediaFile $mediaFile, Context $context)
    {
        if (!$media->getHasFile()) {
            return;
        }

        if ($mediaFile->getFileExtension() === $media->getFileExtension()) {
            return;
        }

        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($media->getId(), $media->getFileExtension());
        $this->filesystem->delete($oldMediaFilePath);

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, string $mediaId): void
    {
        $stream = fopen($mediaFile->getFileName(), 'r');
        $path = $this->urlGenerator->getRelativeMediaUrl($mediaId, $mediaFile->getFileExtension());
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
            'metaData' => $metadata,
            'hasFile' => true,
        ];

        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->repository->update([$data], $context);

        $media = new MediaStruct();
        $media->assign($data);

        return $media->assign($data);
    }
}
