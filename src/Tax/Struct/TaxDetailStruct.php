<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Product\Collection\ProductBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class TaxDetailStruct extends TaxBasicStruct
{
    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $areaRules;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();

        $this->areaRules = new TaxAreaRuleBasicCollection();
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }

    public function getAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->areaRules;
    }

    public function setAreaRules(TaxAreaRuleBasicCollection $areaRules): void
    {
        $this->areaRules = $areaRules;
    }
}
