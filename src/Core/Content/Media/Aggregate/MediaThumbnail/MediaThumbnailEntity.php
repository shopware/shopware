<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaThumbnailEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $path = null;

    protected int $width;

    protected int $height;

    protected ?string $url = '';

    /**
     * @deprecated tag:v6.8.0 - Will be non-nullable
     */
    protected ?string $mediaId;

    protected ?MediaEntity $media = null;

    /**
     * @deprecated tag:v6.8.0 - Will be non-nullable
     */
    protected ?string $mediaThumbnailSizeId = null;

    protected ?MediaThumbnailSizeEntity $mediaThumbnailSize = null;

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

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string')]
    public function getUrl(): string
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if ($this->url === null) {
            return '';
        }

        return $this->url;
    }

    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'url', newType: '?string')]
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getMediaId(): string
    {
        if (!isset($this->mediaId)) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', '$mediaId must not be null');

            return '';
        }

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

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getMediaThumbnailSizeId(): ?string
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if (!isset($this->mediaThumbnailSizeId)) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', '$mediaThumbnailSizeId must not be null');

            return null;
        }

        return $this->mediaThumbnailSizeId;
    }

    public function setMediaThumbnailSizeId(string $mediaThumbnailSizeId): void
    {
        $this->mediaThumbnailSizeId = $mediaThumbnailSizeId;
    }

    public function getMediaThumbnailSize(): ?MediaThumbnailSizeEntity
    {
        return $this->mediaThumbnailSize;
    }

    public function setMediaThumbnailSize(MediaThumbnailSizeEntity $mediaThumbnailSize): void
    {
        $this->mediaThumbnailSize = $mediaThumbnailSize;
    }

    public function getIdentifier(): string
    {
        return \sprintf('%dx%d', $this->getWidth(), $this->getHeight());
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }
}
