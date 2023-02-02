<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class CartPositionStruct extends Struct
{
    /**
     * @var float
     */
    protected $netPrice;

    /**
     * @var float
     */
    protected $taxValue;

    /**
     * @var float
     */
    protected $grossPrice;

    /**
     * @var float
     */
    protected $pseudoPrice;

    /**
     * @var bool
     */
    protected $firstMonthFree;

    /**
     * @var int
     */
    protected $discountAppliesForMonths;

    /**
     * @var array
     */
    protected $extension;

    /**
     * @var array
     */
    protected $variant;

    public static function fromArray(array $data): CartPositionStruct
    {
        return (new self())->assign($data);
    }

    public function getExtensionInformation(): array
    {
        return $this->extension;
    }

    public function setExtensionInformation(array $extensionInformation): void
    {
        $this->extension = $extensionInformation;
    }

    public function getExtensionId(): int
    {
        return $this->getExtensionInformation()['id'];
    }

    public function getExtensionName(): string
    {
        return $this->getExtensionInformation()['name'];
    }

    public function getVariant(): array
    {
        return $this->variant;
    }

    public function setVariant(array $variant): void
    {
        $this->variant = $variant;
    }

    public function getVariantId(): int
    {
        return $this->getVariant()['id'];
    }

    public function getVariantType(): string
    {
        return $this->getVariant()['name'];
    }

    public function getNetPrice(): float
    {
        return $this->netPrice;
    }

    public function setNetPrice(float $netPrice): void
    {
        $this->netPrice = $netPrice;
    }

    public function getTaxValue(): float
    {
        return $this->taxValue;
    }

    public function setTaxValue(float $taxValue): void
    {
        $this->taxValue = $taxValue;
    }

    public function getGrossPrice(): float
    {
        return $this->grossPrice;
    }

    public function setGrossPrice(float $grossPrice): void
    {
        $this->grossPrice = $grossPrice;
    }

    public function getPseudoPrice(): float
    {
        return $this->pseudoPrice;
    }

    public function setPseudoPrice(float $pseudoPrice): void
    {
        $this->pseudoPrice = $pseudoPrice;
    }

    public function isFirstMonthFree(): bool
    {
        return $this->firstMonthFree;
    }

    public function setFirstMonthFree(bool $firstMonthFree): void
    {
        $this->firstMonthFree = $firstMonthFree;
    }

    public function getDiscountAppliesForMonths(): int
    {
        return $this->discountAppliesForMonths;
    }

    public function setDiscountAppliesForMonths(int $discountAppliesForMonths): void
    {
        $this->discountAppliesForMonths = $discountAppliesForMonths;
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        unset($vars['extensions']);

        return $vars;
    }
}
