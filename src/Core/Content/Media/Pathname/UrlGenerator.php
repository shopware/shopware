<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.6.0 - Use AbstractMediaUrlGenerator instead
 */
#[Package('buyers-experience')]
class UrlGenerator implements UrlGeneratorInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PathnameStrategyInterface $pathnameStrategy,
        private readonly FilesystemOperator $filesystem,
        private readonly AbstractMediaUrlGenerator $generator
    ) {
    }

    /**
     * @throws MediaException
     */
    public function getRelativeMediaUrl(MediaEntity $media): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use AbstractUrlGenerator instead')
        );

        // already migrated? delegate to new service
        if ($media->getPath()) {
            return $media->getPath();
        }

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
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use AbstractUrlGenerator instead')
        );

        // already migrated? delegate to new service
        if ($media->getPath()) {
            $params = ['path' => $media->getPath(), 'updatedAt' => $media->getUpdatedAt()];

            $url = $this->generator->generate([$params]);

            return $url[0];
        }

        return $this->filesystem->publicUrl($this->getRelativeMediaUrl($media));
    }

    /**
     * @throws MediaException
     */
    public function getRelativeThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use AbstractUrlGenerator instead')
        );

        // already migrated?
        if ($thumbnail->getPath()) {
            return $thumbnail->getPath();
        }

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
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use AbstractUrlGenerator instead')
        );

        // already migrated? delegate to new service
        if ($thumbnail->getPath()) {
            $params = ['path' => $thumbnail->getPath(), 'updatedAt' => $thumbnail->getUpdatedAt()];

            $url = $this->generator->generate([$params]);

            return $url[0];
        }

        return $this->filesystem->publicUrl($this->getRelativeThumbnailUrl($media, $thumbnail));
    }

    public function reset(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', )
        );
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
