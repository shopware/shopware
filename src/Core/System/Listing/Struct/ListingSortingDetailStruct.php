<?php declare(strict_types=1);

namespace Shopware\System\Listing\Struct;

use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;

class ListingSortingDetailStruct extends ListingSortingBasicStruct
{
    /**
     * @var ListingSortingTranslationBasicCollection
     */
    protected $translations;

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
}
