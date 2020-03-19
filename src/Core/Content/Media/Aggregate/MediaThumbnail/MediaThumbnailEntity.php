<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaThumbnailEntity extends Entity
{
    use EntityIdTrait;

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
    protected $url = '';

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var array|null
     */
    protected $customFields;

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

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getIdentifier(): string
    {
        $identifier = sprintf('%dx%d', $this->getWidth(), $this->getHeight());

        return $identifier;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getApiAlias(): string
    {
        return 'media_thumbnail';
    }
}
