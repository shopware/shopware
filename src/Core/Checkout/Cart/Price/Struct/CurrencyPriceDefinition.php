<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection as RawPriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('checkout')]
class CurrencyPriceDefinition extends Struct implements PriceDefinitionInterface
{
    final public const TYPE = 'currency-price';
    final public const SORTING_PRIORITY = 75;

    public function __construct(
        protected RawPriceCollection $price,
        /**
         * Allows to define a filter rule which line items should be considered for percentage discount/surcharge
         */
        protected ?Rule $filter = null
    ) {
    }

    public function getFilter(): ?Rule
    {
        return $this->filter;
    }

    public function getPrice(): RawPriceCollection
    {
        return $this->price;
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
            'price' => [new NotBlank(), new Type('array')],
        ];
    }

    public function getApiAlias(): string
    {
        return 'cart_currency_price_definition';
    }
}
