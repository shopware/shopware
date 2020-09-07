<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MediaTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $alt;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): void
    {
        $this->alt = $alt;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
