<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Struct\Struct;

class ImageSliderItemStruct extends Struct
{
    /**
     * @var string|null
     */
    protected $url;

    /**
     * @var bool|null
     */
    protected $newTab;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getNewTab(): ?bool
    {
        return $this->newTab;
    }

    public function setNewTab(?bool $newTab): void
    {
        $this->newTab = $newTab;
    }

    public function getApiAlias(): string
    {
        return 'cms_image_slider_item';
    }
}
