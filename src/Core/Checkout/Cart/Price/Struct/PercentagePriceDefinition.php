<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * A PercentagePriceDefinition calculate a percentual sum of all previously calculated prices and returns it as its own
 * price. This can be used for percentual discounts.
 */
class PercentagePriceDefinition extends Struct implements PriceDefinitionInterface
{
    public const TYPE = 'percentage';
    public const SORTING_PRIORITY = 50;

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
     * @deprecated tag:v6.4.0 - `$precision` parameter will be removed
     *
     * @var int
     */
    protected $precision;

    /**
     * @deprecated tag:v6.4.0 - `$precision` parameter will be removed. Use `create` instead
     */
    public function __construct(float $percentage, int $precision = 2, ?Rule $filter = null)
    {
        $this->percentage = $percentage;
        $this->filter = $filter;
        $this->precision = $precision;
    }

    public static function create(float $percentage, ?Rule $filter = null)
    {
        return new self($percentage, 2, $filter);
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }

    /**
     * @deprecated tag:v6.4.0 - `$precision` will be removed
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getPriority(): int
    {
        return self::SORTING_PRIORITY;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['type'] = $this->getType();

        return $data;
    }

    public static function getConstraints(): array
    {
        return [
            'percentage' => [new NotBlank(), new Type('numeric')],
        ];
    }

    public function getApiAlias(): string
    {
        return 'cart_price_percentage';
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed. Currency precision will only be tracked in CashRoundingConfig.
     */
    public function setPrecision(int $precision): void
    {
        $this->precision = $precision;
    }
}
