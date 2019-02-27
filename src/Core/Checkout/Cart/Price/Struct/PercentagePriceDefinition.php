<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;

class PercentagePriceDefinition extends Struct implements PriceDefinitionInterface
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

    /**
     * @var int
     */
    protected $precision;

    public function __construct(float $percentage, int $precision, ?Rule $filter = null)
    {
        $this->percentage = $percentage;
        $this->filter = $filter;
        $this->precision = $precision;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }
}
