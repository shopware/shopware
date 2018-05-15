<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Api\Product\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerDetailStruct extends ProductManufacturerBasicStruct
{
    /**
     * @var MediaBasicStruct|null
     */
    protected $media;

    /**
     * @var ProductManufacturerTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new ProductManufacturerTranslationBasicCollection();
    }

    public function getMedia(): ?MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(?MediaBasicStruct $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductManufacturerTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
