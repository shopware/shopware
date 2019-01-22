<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Exception\CouldNotRenameFileException;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\MissingFileException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class FileSaver
{
    /**
     * @var EntityRepositoryInterface
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

    /**
     * @var TypeDetector
     */
    private $typeDetector;

    /**
     * @var FileNameValidator
     */
    private $fileNameValidator;

    public function __construct(
        EntityRepositoryInterface $mediaRepository,
        FilesystemInterface $filesystem,
        UrlGeneratorInterface $urlGenerator,
        ThumbnailService $thumbnailService,
        MetadataLoader $metadataLoader,
        TypeDetector $typeDetector
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailService = $thumbnailService;
        $this->metadataLoader = $metadataLoader;
        $this->typeDetector = $typeDetector;
        $this->fileNameValidator = new FileNameValidator();
    }

    /**
     * @throws MediaNotFoundException
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     */
    public function persistFileToMedia(MediaFile $mediaFile, string $destination, string $mediaId, Context $context): void
    {
        $destination = rtrim($destination);
        $this->fileNameValidator->validateFileName($destination);

        $mediaWithRelatedFilename = $this->searchMediaByFilename($mediaId, $destination, $context);
        $currentMedia = $this->popCurrentMedia($mediaWithRelatedFilename, $mediaId);

        $destination = $this->getPossibleFileName($mediaWithRelatedFilename, $mediaId, $destination);

        $this->removeOldMediaData($currentMedia, $context);
        $mediaType = $this->typeDetector->detect($mediaFile);
        $rawMetadata = $this->metadataLoader->loadFromFile($mediaFile, $mediaType);

        $media = $this->updateMediaEntity($mediaFile, $destination, $currentMedia, $rawMetadata, $mediaType, $context);
        $this->saveFileToMediaDir($mediaFile, $media);

        $this->thumbnailService->updateThumbnailsAfterUpload($media, $context);
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws DuplicatedMediaFileNameException
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws MediaNotFoundException
     * @throws MissingFileException
     * @throws EmptyMediaFilenameException
     * @throws IllegalFileNameException
     */
    public function renameMedia(string $mediaId, string $destination, Context $context): void
    {
        $destination = rtrim($destination);
        $this->fileNameValidator->validateFileName($destination);

        $mediaWithRelatedFileName = $this->searchMediaByFilename($mediaId, $destination, $context);
        $currentMedia = $this->popCurrentMedia($mediaWithRelatedFileName, $mediaId);

        if (!$currentMedia->hasFile()) {
            throw new MissingFileException($mediaId);
        }

        if ($destination === $currentMedia->getFileName()) {
            return;
        }

        foreach ($mediaWithRelatedFileName as $media) {
            if ($media->hasFile()) {
                if ($destination === $media->getFileName()) {
                    throw new DuplicatedMediaFileNameException($destination);
                }
            }
        }

        $updatedMedia = clone $currentMedia;
        $updatedMedia->setFileName($destination);
        $updatedMedia->setUploadedAt(new \DateTime());

        $renamedFiles = [];
        try {
            $this->renameFile(
                $this->urlGenerator->getRelativeMediaUrl($currentMedia),
                $this->urlGenerator->getRelativeMediaUrl($updatedMedia),
                $renamedFiles
            );
        } catch (\Exception $e) {
            throw new CouldNotRenameFileException($mediaId, $currentMedia->getFileName());
        }

        foreach ($currentMedia->getThumbnails() as $thumbnail) {
            try {
                $this->renameFile(
                    $this->urlGenerator->getRelativeThumbnailUrl(
                        $currentMedia,
                        $thumbnail->getWidth(),
                        $thumbnail->getHeight()
                    ),
                    $this->urlGenerator->getRelativeThumbnailUrl(
                        $updatedMedia,
                        $thumbnail->getWidth(),
                        $thumbnail->getHeight()
                    ),
                    $renamedFiles
                );
            } catch (\Exception $e) {
                $this->rollbackRenameAction($currentMedia, $renamedFiles);
            }
        }

        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $updateData = [
            'id' => $updatedMedia->getId(),
            'fileName' => $updatedMedia->getFileName(),
            'uploadedAt' => $updatedMedia->getUploadedAt(),
        ];

        try {
            $this->mediaRepository->update([$updateData], $context);
        } catch (\Exception $e) {
            $this->rollbackRenameAction($currentMedia, $renamedFiles);
        }
    }

    private function popCurrentMedia(MediaCollection $relatedMedia, $mediaId): MediaEntity
    {
        $currentMedia = $relatedMedia->get($mediaId);
        if ($currentMedia === null) {
            throw new MediaNotFoundException($mediaId);
        }
        $relatedMedia->remove($mediaId);

        return $currentMedia;
    }

    private function searchMediaByFilename(string $mediaId, string $destination, Context $context): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
               new ContainsFilter('fileName', $destination),
               new EqualsFilter('id', $mediaId),
           ]
        ));

        $search = $this->mediaRepository->search($criteria, $context);

        /** @var MediaCollection $mediaCollection */
        $mediaCollection = $search->getEntities();

        return $mediaCollection;
    }

    private function getPossibleFileName(MediaCollection $relatedMedia, string $mediaId, string $preferredFileName): string
    {
        return $this->getNextPossibleFileName($relatedMedia, $mediaId, $preferredFileName, 0);
    }

    private function getNextPossibleFileName(
        MediaCollection $relatedMedia,
        string $mediaId,
        string $preferredFileName,
        int $iteration
    ): string {
        $nextFileName = $preferredFileName . $this->getIterationExtension($iteration);

        /** @var MediaEntity $media */
        foreach ($relatedMedia as $media) {
            if ($media->hasFile()) {
                if ($media->getFileName() === $nextFileName) {
                    return $this->getNextPossibleFileName($relatedMedia, $mediaId, $preferredFileName, $iteration + 1);
                }
            }
        }

        return $nextFileName;
    }

    private function getIterationExtension(int $iteration): string
    {
        return $iteration === 0 ? '' : " ($iteration)";
    }

    private function removeOldMediaData(MediaEntity $media, Context $context): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $this->filesystem->delete($oldMediaFilePath);

        $this->thumbnailService->deleteThumbnails($media, $context);
    }

    private function saveFileToMediaDir(MediaFile $mediaFile, MediaEntity $media): void
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
        string $destination,
        MediaEntity $media,
        Metadata $metadata,
        MediaType $mediaType,
        Context $context
    ): MediaEntity {
        $data = [
            'id' => $media->getId(),
            'mimeType' => $mediaFile->getMimeType(),
            'fileExtension' => $mediaFile->getFileExtension(),
            'fileSize' => $mediaFile->getFileSize(),
            'fileName' => $destination,
            'metaData' => $metadata,
            'mediaType' => $mediaType,
            'uploadedAt' => new \DateTime(),
        ];

        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->mediaRepository->update([$data], $context);

        return $this->mediaRepository->read(new ReadCriteria([$media->getId()]), $context)->get($media->getId());
    }

    /**
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function renameFile($source, $destination, array &$fileNames): void
    {
        $this->filesystem->rename($source, $destination);
        $fileNames[$source] = $destination;
    }

    /**
     * @throws CouldNotRenameFileException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    private function rollbackRenameAction(MediaEntity $oldMedia, array $renamedFiles): void
    {
        foreach ($renamedFiles as $oldFileName => $newFileName) {
            $this->filesystem->rename($newFileName, $oldFileName);
        }

        throw new CouldNotRenameFileException($oldMedia->getId(), $oldMedia->getFileName());
    }
}
