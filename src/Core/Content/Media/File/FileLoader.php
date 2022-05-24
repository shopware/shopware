<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\StreamNotReadableException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class FileLoader
{
    private FilesystemInterface $filesystemPublic;

    private FilesystemInterface $filesystemPrivate;

    private UrlGeneratorInterface $urlGenerator;

    private FileNameValidator $fileNameValidator;

    private EntityRepositoryInterface $mediaRepository;

    private StreamFactoryInterface $streamFactory;

    /**
     * @internal
     */
    public function __construct(
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator,
        EntityRepositoryInterface $mediaRepository,
        StreamFactoryInterface $streamFactory
    ) {
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->fileNameValidator = new FileNameValidator();
        $this->mediaRepository = $mediaRepository;
        $this->streamFactory = $streamFactory;
    }

    public function loadMediaFile(string $mediaId, Context $context): string
    {
        $media = $this->findMediaById($mediaId, $context);

        return $this->getFileSystem($media)->read($this->getFilePath($media)) ?: '';
    }

    public function loadMediaFileStream(string $mediaId, Context $context): StreamInterface
    {
        $media = $this->findMediaById($mediaId, $context);
        $resource = $this->getFileSystem($media)->readStream($this->getFilePath($media));
        if ($resource === false) {
            throw new StreamNotReadableException($this->getFilePath($media));
        }

        return $this->streamFactory->createStreamFromResource($resource);
    }

    private function getFilePath(MediaEntity $media): string
    {
        $this->fileNameValidator->validateFileName($media->getFileName() ?: '');

        return $this->urlGenerator->getRelativeMediaUrl($media);
    }

    private function getFileSystem(MediaEntity $media): FilesystemInterface
    {
        if ($media->isPrivate()) {
            return $this->filesystemPrivate;
        }

        return $this->filesystemPublic;
    }

    /**
     * @throws MediaNotFoundException
     */
    private function findMediaById(string $mediaId, Context $context): MediaEntity
    {
        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('mediaFolder');
        $currentMedia = $this->mediaRepository
            ->search($criteria, $context)
            ->get($mediaId);

        if ($currentMedia === null) {
            throw new MediaNotFoundException($mediaId);
        }

        return $currentMedia;
    }
}
