<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * A QuantityPriceDefinition is the most common PriceDefinition type and is used for all prices which increase or decrease
 * based on a item quantity. These Definitions are used for LineItems created from Products. They do not depend on
 * other PriceDefinitions in a calculation process.
 */
class QuantityPriceDefinition extends Struct implements PriceDefinitionInterface
{
    public const TYPE = 'quantity';
    public const SORTING_PRIORITY = 100;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var TaxRuleCollection
     */
    protected $taxRules;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var bool
     */
    protected $isCalculated;

    /**
     * @var int
     */
    protected $precision;

    /**
     * @var ReferencePriceDefinition|null
     */
    protected $referencePriceDefinition;

    /**
     * @var float|null
     */
    protected $listPrice;

    public function __construct(
        float $price,
        TaxRuleCollection $taxRules,
        int $precision,
        int $quantity = 1,
        bool $isCalculated = false,
        ?ReferencePriceDefinition $referencePrice = null,
        ?float $listPrice = null
    ) {
        $this->price = FloatComparator::cast($price);
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
        $this->isCalculated = $isCalculated;
        $this->precision = $precision;
        $this->referencePriceDefinition = $referencePrice;
        $this->listPrice = $listPrice;
    }

    public function getPrice(): float
    {
        return FloatComparator::cast($this->price);
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function isCalculated(): bool
    {
        return $this->isCalculated;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function setPrecision(int $precision): void
    {
        $this->precision = $precision;
    }

    public static function fromArray(array $data): self
    {
        $taxRules = array_map(
            function (array $tax) {
                return new TaxRule(
                    (float) $tax['taxRate'],
                    (float) $tax['percentage']
                );
            },
            $data['taxRules']
        );

        return new self(
            (float) $data['price'],
            new TaxRuleCollection($taxRules),
            (int) $data['precision'],
            \array_key_exists('quantity', $data) ? $data['quantity'] : 1,
            \array_key_exists('isCalculated', $data) ? $data['isCalculated'] : false,
            null,
            isset($data['listPrice']) ? (float) $data['listPrice'] : null
        );
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['type'] = $this->getType();

        return $data;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getPriority(): int
    {
        return self::SORTING_PRIORITY;
    }

    public static function getConstraints(): array
    {
        return [
            'price' => [new NotBlank(), new Type('numeric')],
            'quantity' => [new Type('int')],
            'isCalculated' => [new Type('bool')],
            'precision' => [new NotBlank(), new Type('int')],
        ];
    }

    public function getReferencePriceDefinition(): ?ReferencePriceDefinition
    {
        return $this->referencePriceDefinition;
    }

    public function getListPrice(): ?float
    {
        return $this->listPrice ? FloatComparator::cast($this->listPrice) : null;
    }

    public function setListPrice(?float $listPrice): void
    {
        $listPrice = $listPrice ? FloatComparator::cast($listPrice) : null;
        $this->listPrice = $listPrice;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_quantity';
    }
}
