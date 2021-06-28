<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\HttpFoundation\RequestStack;

class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var PathnameStrategyInterface
     */
    private $pathnameStrategy;

    public function __construct(
        PathnameStrategyInterface $pathnameStrategy,
        RequestStack $requestStack,
        ?string $baseUrl = null
    ) {
        $this->pathnameStrategy = $pathnameStrategy;
        $this->requestStack = $requestStack;

        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
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
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getAbsoluteMediaUrl(MediaEntity $media): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeMediaUrl($media);
    }

    /**
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
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
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function getAbsoluteThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeThumbnailUrl($media, $thumbnail);
    }

    private function normalizeBaseUrl(?string $baseUrl): ?string
    {
        if ($baseUrl === null) {
            return null;
        }

        return rtrim($baseUrl, '/');
    }

    private function getBaseUrl(): string
    {
        if (!$this->baseUrl) {
            $this->baseUrl = $this->createFallbackUrl();
        }

        return $this->baseUrl;
    }

    private function createFallbackUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request) {
            $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();

            return rtrim($basePath, '/');
        }

        return (string) EnvironmentHelper::getVariable('APP_URL');
    }

    private function toPathString(array $parts): string
    {
        return implode('/', array_filter($parts));
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
