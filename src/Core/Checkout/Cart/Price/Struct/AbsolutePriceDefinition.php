<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * An AbsolutePriceDefinition always return its price value as the final price and adjusts it net worth according to
 * the taxes of other price definitions. This can, for example, be used to create vouchers with a fixed amount.
 */
class AbsolutePriceDefinition extends Struct implements PriceDefinitionInterface
{
    public const TYPE = 'absolute';
    public const SORTING_PRIORITY = 75;

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

    /**
     * @deprecated tag:v6.4.0 - `$precision` parameter will be removed
     *
     * @var int
     */
    protected $precision;

    /**
     * @deprecated tag:v6.4.0 - `$precision` parameter will be removed. Use `create` instead
     */
    public function __construct(float $price, int $precision = 2, ?Rule $filter = null)
    {
        $this->price = $price;
        $this->filter = $filter;
        $this->precision = $precision;
    }

    public static function create(float $price, ?Rule $filter = null)
    {
        return new self($price, 2, $filter);
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @deprecated tag:v6.4.0 - `$precision` parameter will be removed
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
            'price' => [new NotBlank(), new Type('numeric')],
        ];
    }

    public function getApiAlias(): string
    {
        return 'cart_price_absolute';
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed. Currency precision will only be tracked in CashRoundingConfig.
     */
    public function setPrecision(int $precision): void
    {
        $this->precision = $precision;
    }
}
