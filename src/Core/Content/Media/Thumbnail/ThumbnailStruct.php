<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

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
     * @var bool
     */
    protected $highDpi;

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width)
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height)
    {
        $this->height = $height;
    }

    public function isHighDpi(): bool
    {
        return $this->highDpi;
    }

    public function setHighDpi(bool $highDpi)
    {
        $this->highDpi = $highDpi;
    }

    public function get(string $property)
    {
        try {
            return $this->$property;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                sprintf('Property %s do not exist in class %s', $property, get_class($this))
            );
        }
    }
}
