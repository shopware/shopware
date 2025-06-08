<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class ImageSliderStruct extends Struct
{
    protected ?array $navigation = null;

    /**
     * @var ImageSliderItemStruct[]|null
     */
    protected ?array $sliderItems = [];

    /**
     * @return ImageSliderItemStruct[]|null
     */
    public function getSliderItems(): ?array
    {
        return $this->sliderItems;
    }

    /**
     * @param ImageSliderItemStruct[]|null $sliderItems
     */
    public function setSliderItems(?array $sliderItems): void
    {
        $this->sliderItems = $sliderItems;
    }

    public function addSliderItem(ImageSliderItemStruct $sliderItem): void
    {
        $this->sliderItems[] = $sliderItem;
    }

    public function getNavigation(): ?array
    {
        return $this->navigation;
    }

    public function setNavigation(?array $navigation): void
    {
        $this->navigation = $navigation;
    }

    public function getApiAlias(): string
    {
        return 'cms_image_slider';
    }
}
