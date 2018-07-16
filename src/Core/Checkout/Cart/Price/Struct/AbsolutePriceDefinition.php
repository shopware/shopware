<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;

class AbsolutePriceDefinition implements PriceDefinitionInterface
{
    /**
     * @var float
     */
    protected $price;

    /**
     * Allows to define a filter rule which line items should be considered for percentage discount/surcharge
     *
     * @var Rule|null
     */
    protected $filter;

    public function __construct(float $price, ?Rule $filter)
    {
        $this->price = $price;
        $this->filter = $filter;
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
