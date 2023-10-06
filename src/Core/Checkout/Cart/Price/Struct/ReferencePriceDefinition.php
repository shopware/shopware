<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
class ReferencePriceDefinition extends Struct
{
    /**
     * @var float
     */
    protected $purchaseUnit;

    /**
     * @var float
     */
    protected $referenceUnit;

    /**
     * @var string
     */
    protected $unitName;

    public function __construct(
        float $purchaseUnit,
        float $referenceUnit,
        string $unitName
    ) {
        $this->purchaseUnit = FloatComparator::cast($purchaseUnit);
        $this->referenceUnit = FloatComparator::cast($referenceUnit);
        $this->unitName = $unitName;
    }

    public function getPurchaseUnit(): float
    {
        return FloatComparator::cast($this->purchaseUnit);
    }

    public function getReferenceUnit(): float
    {
        return FloatComparator::cast($this->referenceUnit);
    }

    public function getUnitName(): string
    {
        return $this->unitName;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_reference_definition';
    }
}
