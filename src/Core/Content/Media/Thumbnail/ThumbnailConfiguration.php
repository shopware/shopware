<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Shopware\Core\Framework\Struct\Struct;

class ThumbnailConfiguration extends Struct
{
    /**
     * @var array
     */
    private $blacklistedMimeTypes;

    /**
     * @var array
     */
    private $supportedMimeTypes;

    /**
     * @var array
     */
    private $thumbnailSizes;

    /**
     * @var bool
     */
    private $highDpi;

    /**
     * @var int
     */
    private $standardQuality;

    /**
     * @var int
     */
    private $highDpiQuality;

    /**
     * @var bool
     */
    private $keepProportions;

    /**
     * @var bool
     */
    private $autoGenerateAfterUpload;

    public static function getSizeArray(int $width, int $height): array
    {
        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function getDefaultThumbnailConfiguration(): self
    {
        $default = new self();
        $default->thumbnailSizes = [
            self::getSizeArray(140, 140),
            self::getSizeArray(300, 300),
        ];

        $default->highDpi = false;
        $default->standardQuality = 90;
        $default->highDpiQuality = 90;
        $default->keepProportions = true;
        $default->autoGenerateAfterUpload = true;
        $default->blacklistedMimeTypes = ['/^image\/svg\+xml$/'];
        $default->supportedMimeTypes = ['/^image\/.+$/'];

        return $default;
    }

    public function isMimeTypeSupported(?string $mimeType): bool
    {
        if (!$mimeType) {
            return false;
        }

        foreach ($this->blacklistedMimeTypes as $blacklistedMimeType) {
            if (preg_match($blacklistedMimeType, $mimeType)) {
                return false;
            }
        }

        foreach ($this->supportedMimeTypes as $supportedMimeType) {
            if (preg_match($supportedMimeType, $mimeType) === 1) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedMimeTypes(): array
    {
        return $this->supportedMimeTypes;
    }

    public function setSupportedMimeTypes(array $supportedMimeTypes): void
    {
        $this->supportedMimeTypes = $supportedMimeTypes;
    }

    public function getThumbnailSizes(): array
    {
        return $this->thumbnailSizes;
    }

    public function setThumbnailSizes(array $thumbnailSizes): void
    {
        $this->thumbnailSizes = $thumbnailSizes;
    }

    public function isHighDpi(): bool
    {
        return $this->highDpi;
    }

    public function setHighDpi(bool $highDpi): void
    {
        $this->highDpi = $highDpi;
    }

    public function getStandardQuality(): int
    {
        return $this->standardQuality;
    }

    public function setStandardQuality(int $standardQuality): void
    {
        $this->standardQuality = $standardQuality;
    }

    public function getHighDpiQuality(): int
    {
        return $this->highDpiQuality;
    }

    public function setHighDpiQuality(int $highDpiQuality): void
    {
        $this->highDpiQuality = $highDpiQuality;
    }

    public function isKeepProportions(): bool
    {
        return $this->keepProportions;
    }

    public function setKeepProportions(bool $keepProportions): void
    {
        $this->keepProportions = $keepProportions;
    }

    public function isAutoGenerateAfterUpload(): bool
    {
        return $this->autoGenerateAfterUpload;
    }

    public function setAutoGenerateAfterUpload(bool $autoGenerateAfterUpload): void
    {
        $this->autoGenerateAfterUpload = $autoGenerateAfterUpload;
    }
}
