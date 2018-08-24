<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
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
    private $baseUrl = null;

    /**
     * @var StrategyInterface
     */
    private $strategy;

    public function __construct(StrategyInterface $strategy, RequestStack $requestStack, string $baseUrl = null)
    {
        $this->strategy = $strategy;
        $this->requestStack = $requestStack;

        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getMediaUrl(string $filename, string $extension, bool $absolute = true): string
    {
        $encodedFileName = $this->encodeFilename($filename);

        $basePath = $absolute ? $this->getBaseUrl() . '/' : '';

        return $basePath . 'media/' . $encodedFileName . '.' . $extension;
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getThumbnailUrl(
        string $filename,
        string $extension,
        int $width,
        int $height,
        bool $isHighDpi = false,
        bool $absolute = true): string
    {
        $encodedFileName = $this->encodeFilename($filename);
        $thumbnailExtension = "_${width}x${height}";
        if ($isHighDpi) {
            $thumbnailExtension .= '@2x';
        }

        $basePath = $absolute ? $this->getBaseUrl() . '/' : '';

        return $basePath . 'thumbnail/' . $encodedFileName . $thumbnailExtension . '.' . $extension;
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
    private function encodeFilename(string $filename): string
    {
        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        return $this->strategy->encode($filename);
    }
}
