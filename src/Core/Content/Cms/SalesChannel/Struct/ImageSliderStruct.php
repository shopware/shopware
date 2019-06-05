<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ImageSliderStruct extends Struct
{
    /**
     * @var ImageSliderItemStruct[]|null
     */
    protected $sliderItems = [];

    /**
     * @var array|null
     */
    protected $navigation;

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
}
