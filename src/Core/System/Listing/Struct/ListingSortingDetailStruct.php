<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;

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
