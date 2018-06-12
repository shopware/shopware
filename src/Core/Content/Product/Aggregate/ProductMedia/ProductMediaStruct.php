<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Framework\ORM\Entity;

class ProductMediaStruct extends Entity
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var bool
     */
    protected $isCover;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var MediaStruct
     */
    protected $media;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getIsCover(): bool
    {
        return $this->isCover;
    }

    public function setIsCover(bool $isCover): void
    {
        $this->isCover = $isCover;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getMedia(): MediaStruct
    {
        return $this->media;
    }

    public function setMedia(MediaStruct $media): void
    {
        $this->media = $media;
    }
}
