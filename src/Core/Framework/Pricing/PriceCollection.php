<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

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
