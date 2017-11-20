<?php declare(strict_types=1);

namespace Shopware\Unit\Struct;

use Shopware\Product\Collection\ProductBasicCollection;
use Shopware\Unit\Collection\UnitTranslationBasicCollection;

class UnitDetailStruct extends UnitBasicStruct
{
    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var UnitTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();

        $this->translations = new UnitTranslationBasicCollection();
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }

    public function getTranslations(): UnitTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(UnitTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
