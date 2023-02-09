<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

#[Package('content')]
class UrlGenerator implements UrlGeneratorInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractPathGenerator $pathGenerator,
        private readonly FilesystemOperator $filesystem
    ) {
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getRelativeMediaUrl(MediaEntity $media): string
    {
        $this->validateMedia($media);

        if (empty($media->getPath())) {
            return $this->pathGenerator->generatePath($media);
        }

        return $media->getPath();
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getAbsoluteMediaUrl(MediaEntity $media): string
    {
        return $this->filesystem->publicUrl($this->getRelativeMediaUrl($media));
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getRelativeThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        $this->validateMedia($media);

        if (empty($thumbnail->getPath())) {
            return $this->pathGenerator->generatePath($media, $thumbnail);
        }

        return $thumbnail->getPath();
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getAbsoluteThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        return $this->filesystem->publicUrl($this->getRelativeThumbnailUrl($media, $thumbnail));
    }

    public function reset(): void
    {
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    private function validateMedia(MediaEntity $media): void
    {
        if (empty($media->getId())) {
            throw new EmptyMediaIdException();
        }

        if (empty($media->getFileName())) {
            throw new EmptyMediaFilenameException();
        }
    }
}
