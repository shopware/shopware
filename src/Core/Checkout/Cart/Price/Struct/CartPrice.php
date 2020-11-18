<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

class CartPrice extends Struct
{
    public const TAX_STATE_GROSS = 'gross';
    public const TAX_STATE_NET = 'net';
    public const TAX_STATE_FREE = 'tax-free';

    /**
     * @var float
     */
    protected $netPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var CalculatedTaxCollection
     */
    protected $calculatedTaxes;

    /**
     * @var TaxRuleCollection
     */
    protected $taxRules;

    /**
     * @var float
     */
    protected $positionPrice;

    /**
     * @var string
     */
    protected $taxStatus;

    /**
     * @var float
     */
    protected $rawTotal;

    public function __construct(
        float $netPrice,
        float $totalPrice,
        float $positionPrice,
        CalculatedTaxCollection $calculatedTaxes,
        TaxRuleCollection $taxRules,
        string $taxStatus,
        ?float $rawTotal = null
    ) {
        $this->netPrice = FloatComparator::cast($netPrice);
        $this->totalPrice = FloatComparator::cast($totalPrice);
        $this->calculatedTaxes = $calculatedTaxes;
        $this->taxRules = $taxRules;
        $this->positionPrice = $positionPrice;
        $this->taxStatus = $taxStatus;
        $rawTotal = $rawTotal ?? $totalPrice;
        $this->rawTotal = $rawTotal;
    }

    public function getNetPrice(): float
    {
        return FloatComparator::cast($this->netPrice);
    }

    public function getTotalPrice(): float
    {
        return FloatComparator::cast($this->totalPrice);
    }

    public function getCalculatedTaxes(): CalculatedTaxCollection
    {
        return $this->calculatedTaxes;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getPositionPrice(): float
    {
        return $this->positionPrice;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }

    public function hasNetPrices(): bool
    {
        return \in_array($this->taxStatus, [self::TAX_STATE_NET, self::TAX_STATE_FREE], true);
    }

    public function isTaxFree(): bool
    {
        return $this->taxStatus === self::TAX_STATE_FREE;
    }

    public static function createEmpty(string $taxState = self::TAX_STATE_GROSS): CartPrice
    {
        return new self(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), $taxState);
    }

    public function getApiAlias(): string
    {
        return 'cart_price';
    }

    public function getRawTotal(): float
    {
        return $this->rawTotal;
    }
}
