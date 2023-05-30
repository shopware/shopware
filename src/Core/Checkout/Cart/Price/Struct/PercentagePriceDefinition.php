<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * A PercentagePriceDefinition calculate a percentual sum of all previously calculated prices and returns it as its own
 * price. This can be used for percentual discounts.
 */
#[Package('checkout')]
class PercentagePriceDefinition extends Struct implements PriceDefinitionInterface
{
    final public const TYPE = 'percentage';
    final public const SORTING_PRIORITY = 50;

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

    public function __construct(
        float $percentage,
        ?Rule $filter = null
    ) {
        $this->percentage = FloatComparator::cast($percentage);
        $this->filter = $filter;
    }

    public function getPercentage(): float
    {
        return FloatComparator::cast($this->percentage);
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
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
}
