<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;

class PercentagePriceDefinition implements PriceDefinition
{
    /**
     * @var float
     */
    protected $percentage;

    /**
     * Allows to define a filter rule which line items should be considered for percentage discount/surcharge
     *
     * @var Rule|null
     */
    protected $filter;

    public function __construct(float $percentage, ?Rule $filter)
    {
        $this->percentage = $percentage;
        $this->filter = $filter;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }
}