<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

#[Package('buyers-experience')]
class UrlGenerator implements UrlGeneratorInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PathnameStrategyInterface $pathnameStrategy,
        private readonly FilesystemOperator $filesystem
    ) {
    }

    /**
     * @throws MediaException
     */
    public function getRelativeMediaUrl(MediaEntity $media): string
    {
        $this->validateMedia($media);

        return $this->toPathString([
            'media',
            $this->pathnameStrategy->generatePathHash($media),
            $this->pathnameStrategy->generatePathCacheBuster($media),
            $this->pathnameStrategy->generatePhysicalFilename($media),
        ]);
    }

    /**
     * @throws MediaException
     */
    public function getAbsoluteMediaUrl(MediaEntity $media): string
    {
        return $this->filesystem->publicUrl($this->getRelativeMediaUrl($media));
    }

    /**
     * @throws MediaException
     */
    public function getRelativeThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        $this->validateMedia($media);

        return $this->toPathString([
            'thumbnail',
            $this->pathnameStrategy->generatePathHash($media),
            $this->pathnameStrategy->generatePathCacheBuster($media),
            $this->pathnameStrategy->generatePhysicalFilename($media, $thumbnail),
        ]);
    }

    /**
     * @throws MediaException
     */
    public function getAbsoluteThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        return $this->filesystem->publicUrl($this->getRelativeThumbnailUrl($media, $thumbnail));
    }

    public function reset(): void
    {
    }

    /**
     * @param array<string|null> $parts
     */
    private function toPathString(array $parts): string
    {
        return implode('/', array_filter($parts));
    }

    /**
     * @throws MediaException
     */
    private function validateMedia(MediaEntity $media): void
    {
        if (empty($media->getId())) {
            throw MediaException::emptyMediaId();
        }

        if (empty($media->getFileName())) {
            throw MediaException::emptyMediaFilename();
        }
    }
}
