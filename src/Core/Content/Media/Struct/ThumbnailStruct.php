<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ThumbnailStruct extends Struct
{
    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var bool
     */
    protected $highDpi;

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @param int $width
     */
    public function setWidth(int $width)
    {
        $this->width = $width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param int $height
     */
    public function setHeight(int $height)
    {
        $this->height = $height;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     */
    public function setFileName(string $fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * @return bool
     */
    public function isHighDpi(): bool
    {
        return $this->highDpi;
    }

    /**
     * @param bool $highDpi
     */
    public function setHighDpi(bool $highDpi)
    {
        $this->highDpi = $highDpi;
    }
}
