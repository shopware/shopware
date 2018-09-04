<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class ImageMetadata extends MetadataType
{
    /**
     * @var int|null
     */
    protected $width = self::UNKNOWN;

    /**
     * @var int|null
     */
    protected $height = self::UNKNOWN;

    public static function getValidFileExtensions(): array
    {
        return [
            'jpg',
            'gif',
            'png',
        ];
    }

    public static function create(): MetadataType
    {
        return new self();
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getName(): string
    {
        return 'image';
    }
}
