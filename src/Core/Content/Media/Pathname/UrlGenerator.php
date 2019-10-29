<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
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
     */
    public function getRelativeMediaUrl(MediaEntity $media): string
    {
        $physicalFileName = $media->getFileName();

        if ($media->getUploadedAt() !== null) {
            $physicalFileName = sprintf('%d/%s', $media->getUploadedAt()->getTimestamp(), $physicalFileName);
        }
        $encodedFileName = $this->encodeFilename($physicalFileName, $media->getId());

        $extension = $media->getFileExtension() ? '.' . $media->getFileExtension() : '';

        $mainDir = 'media/';

        return $mainDir . $encodedFileName . $extension;
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getAbsoluteMediaUrl(MediaEntity $media): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeMediaUrl($media);
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getRelativeThumbnailUrl(MediaEntity $media, int $width, int $height): string
    {
        $mediaPathInfo = pathinfo($this->getRelativeMediaUrl($media));
        $mediaPathInfo['dirname'] = preg_replace('/^media/', 'thumbnail', $mediaPathInfo['dirname']);

        $thumbnailExtension = "_${width}x${height}";

        $extension = isset($mediaPathInfo['extension']) ? '.' . $mediaPathInfo['extension'] : '';

        return $mediaPathInfo['dirname'] . '/' . $mediaPathInfo['filename'] . $thumbnailExtension . $extension;
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getAbsoluteThumbnailUrl(MediaEntity $media, int $width, int $height): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeThumbnailUrl($media, $width, $height);
    }

    private function normalizeBaseUrl($baseUrl): ?string
    {
        if (!$baseUrl) {
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
        $request = $this->requestStack->getMasterRequest();
        if ($request) {
            $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();

            return rtrim($basePath, '/');
        }

        //todo@next: resolve default shop path
        return '';
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    private function encodeFilename(?string $filename, ?string $id): string
    {
        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        return $this->pathnameStrategy->encode($filename, $id);
    }
}
