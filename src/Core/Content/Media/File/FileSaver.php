<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var MetadataLoader
     */
    private $metadataLoader;

    public function __construct(
        RepositoryInterface $repository,
        FilesystemInterface $filesystem,
        UrlGeneratorInterface $urlGenerator,
        EventDispatcherInterface $eventDispatcher,
        MetadataLoader $metadataLoader
    ) {
        $this->repository = $repository;
        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->eventDispatcher = $eventDispatcher;
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
        if ($this->repository->searchIds($criteria, $context)->getTotal() !== 1) {
            throw new MediaNotFoundException($mediaId);
        }

        $rawMetadata = $this->metadataLoader->loadFromFile($mediaFile);
        $this->saveFileToMediaDir($mediaFile, $mediaId);
        $media = $this->updateMediaEntity($mediaFile, $mediaId, $rawMetadata, $context);

        try {
            $this->eventDispatcher->dispatch(
                MediaFileUploadedEvent::EVENT_NAME,
                new MediaFileUploadedEvent($media, $context)
            );
        } catch (FileTypeNotSupportedException $e) {
            //ignore that a thumbnail was not created
        }
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
            'thumbnailsCreated' => false,
        ];

        $context->getWriteProtection()->allow('write_media');
        $this->repository->update([$data], $context);

        $media = new MediaStruct();
        $media->assign($data);

        return $media->assign($data);
    }
}
