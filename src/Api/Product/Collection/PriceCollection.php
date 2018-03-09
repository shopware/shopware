<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\PriceStruct;
use Shopware\Framework\Struct\Collection;

class PriceCollection extends Collection
{
    /**
     * @var PriceStruct[]
     */
    protected $elements = [];

    public function add(PriceStruct $contextPrice): void
    {
        $this->elements[] = $contextPrice;
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
