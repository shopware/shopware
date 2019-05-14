<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;

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

    public function __construct(
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->fileNameValidator = new FileNameValidator();
    }

    public function loadMediaFile(MediaEntity $media): string
    {
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
}
