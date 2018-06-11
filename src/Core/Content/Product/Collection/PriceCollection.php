<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Collection;

use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Collection;

class PriceCollection extends Collection
{
    /**
     * @var PriceStruct[]
     */
    protected $elements = [];

    public function add(PriceStruct $priceRule): void
    {
        $this->elements[] = $priceRule;
    }

    public function get(string $key): ? PriceStruct
    {
        return $this->elements[$key];
    }

    public function current(): PriceStruct
    {
        return parent::current();
    }
}
