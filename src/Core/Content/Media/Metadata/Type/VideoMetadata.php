<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\Type;

class VideoMetadata extends ImageMetadata
{
    /**
     * @var float|null
     */
    protected $frameRate;

    public function getFrameRate(): ?float
    {
        return $this->frameRate;
    }

    public function setFrameRate(float $frameRate): void
    {
        $this->frameRate = $frameRate;
    }
}
