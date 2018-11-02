<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class MediaThumbnailStruct extends Entity
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

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var MediaStruct
     */
    protected $media;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getHighDpi(): bool
    {
        return $this->highDpi;
    }

    public function setHighDpi(bool $highDpi): void
    {
        $this->highDpi = $highDpi;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): MediaStruct
    {
        return $this->media;
    }

    public function setMedia(MediaStruct $media): void
    {
        $this->media = $media;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getIdentifier(): string
    {
        $identifier = sprintf('%dx%d', $this->getWidth(), $this->getHeight());
        if ($this->getHighDpi()) {
            $identifier .= '@2x';
        }

        return  $identifier;
    }
}
