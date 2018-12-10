<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\Struct\Collection;

class PriceCollection extends Collection
{
    /**
     * @var Price[]
     */
    protected $elements = [];

    public function add(Price $priceRule): void
    {
        $this->elements[] = $priceRule;
    }

    public function get(string $key): ? Price
    {
        return $this->elements[$key];
    }

    public function current(): Price
    {
        return parent::current();
    }
}
