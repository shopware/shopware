<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class QuantityPriceDefinition extends Struct implements PriceDefinitionInterface
{
    public const TYPE = 'quantity';

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

    public function __construct(
        float $price,
        TaxRuleCollection $taxRules,
        int $precision,
        int $quantity = 1,
        bool $isCalculated = false
    ) {
        $this->price = $price;
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
        $this->isCalculated = $isCalculated;
        $this->precision = $precision;
    }

    public function getPrice(): float
    {
        return $this->price;
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
            array_key_exists('quantity', $data) ? $data['quantity'] : 1,
            array_key_exists('isCalculated', $data) ? $data['isCalculated'] : false
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

    public static function getConstraints(): array
    {
        return [
            'price' => [new NotBlank(), new Type('numeric')],
            'quantity' => [new Type('int')],
            'isCalculated' => [new Type('bool')],
            'precision' => [new NotBlank(), new Type('int')],
        ];
    }
}
