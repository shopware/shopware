<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Listing\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Product\Collection\ProductStreamBasicCollection;

class ListingSortingDetailStruct extends ListingSortingBasicStruct
{
    /**
     * @var ListingSortingTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ProductStreamBasicCollection
     */
    protected $productStreams;

    public function __construct()
    {
        $this->translations = new ListingSortingTranslationBasicCollection();

        $this->productStreams = new ProductStreamBasicCollection();
    }

    public function getTranslations(): ListingSortingTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ListingSortingTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return $this->productStreams;
    }

    public function setProductStreams(ProductStreamBasicCollection $productStreams): void
    {
        $this->productStreams = $productStreams;
    }
}
