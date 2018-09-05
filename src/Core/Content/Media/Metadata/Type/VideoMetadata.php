<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class VideoMetadata extends ImageMetadata
{
    /**
     * @var int|null
     */
    protected $frameRate;

    public static function getValidFileExtensions(): array
    {
        return [
            'mp4',
            'avi',
            'webm',
        ];
    }

    public static function create(): MetadataType
    {
        return new VideoMetadata();
    }

    public function getFrameRate(): ?float
    {
        return $this->frameRate;
    }

    public function setFrameRate(float $frameRate): void
    {
        $this->frameRate = $frameRate;
    }

    public function getName(): string
    {
        return 'video';
    }
}
