<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
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
    private $baseUrl = null;

    /**
     * @var PathnameStrategyInterface
     */
    private $strategy;

    public function __construct(
        PathnameStrategyInterface $strategy,
        RequestStack $requestStack,
        string $baseUrl = null
    ) {
        $this->strategy = $strategy;
        $this->requestStack = $requestStack;

        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getRelativeMediaUrl(string $filename, string $extension): string
    {
        $encodedFileName = $this->encodeFilename($filename);

        return 'media/' . $encodedFileName . '.' . $extension;
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getAbsoluteMediaUrl(string $filename, string $extension): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeMediaUrl($filename, $extension);
    }

    public function getThumbnailFolderForFileName($filename): string
    {
        $folderName = pathinfo($this->encodeFilename($filename), PATHINFO_DIRNAME);

        return 'thumbnail/' . $folderName;
    }

    /**
     * @throws EmptyMediaFilenameException
     *
     * @return string
     */
    public function getRelativeThumbnailUrl(string $filename, string $extension, int $width, int $height, bool $isHighDpi = false): string
    {
        $encodedFileName = $this->encodeFilename($filename);
        $thumbnailExtension = "_${width}x${height}";
        if ($isHighDpi) {
            $thumbnailExtension .= '@2x';
        }

        return 'thumbnail/' . $encodedFileName . $thumbnailExtension . '.' . $extension;
    }

    /**
     * @throws EmptyMediaFilenameException
     */
    public function getAbsoluteThumbnailUrl(string $filename, string $extension, int $width, int $height, bool $isHighDpi = false): string
    {
        return $this->getBaseUrl() . '/' . $this->getRelativeThumbnailUrl($filename, $extension, $width, $height, $isHighDpi);
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
