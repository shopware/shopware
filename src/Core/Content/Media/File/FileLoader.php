<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class FileLoader
{
    /**
     * @var FilesystemInterface
     */
    private $filesystemPublic;

    /**
     * @var FilesystemInterface
     */
    private $filesystemPrivate;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var FileNameValidator
     */
    private $fileNameValidator;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->fileNameValidator = new FileNameValidator();
        $this->mediaRepository = $mediaRepository;
    }

    public function loadMediaFile(string $mediaId, Context $context): string
    {
        $media = $this->findMediaById($mediaId, $context);

        $this->fileNameValidator->validateFileName($media->getFileName());
        $path = $this->urlGenerator->getRelativeMediaUrl($media);

        $fileContents = $this->getFileSystem($media)->read($path);

        return $fileContents;
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
