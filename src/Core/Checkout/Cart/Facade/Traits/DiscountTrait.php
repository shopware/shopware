<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Facade\DiscountFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
trait DiscountTrait
{
    private LineItemCollection $items;

    /**
     * The `discount()` methods creates a new discount line-item with the given type and value.
     *
     * @param string $key The id for the new discount.
     * @param string $type The type of the discount, e.g. `percentage`, `absolute`
     * @param float|PriceCollection $value The value of the discount, a float for percentage discounts or a `PriceCollection` for absolute discounts.
     * @param string $label The label of the discount line-item.
     *
     * @return DiscountFacade Returns the newly created discount line-item.
     *
     * @example add-absolute-discount/add-absolute-discount.twig Add an absolute discount to the cart.
     * @example add-simple-discount/add-simple-discount.twig Add a relative discount to the cart.
     */
    public function discount(string $key, string $type, float|PriceCollection $value, string $label): DiscountFacade
    {
        $definition = $this->buildDiscountDefinition($type, $value, $key);

        $item = new LineItem($key, LineItem::DISCOUNT_LINE_ITEM, null, 1);
        $item->setGood(false);
        $item->setRemovable(true);
        $item->setPriceDefinition($definition);
        $item->setLabel($label);
        $item->setRemovable(true);
        $this->getItems()->add($item);

        return new DiscountFacade($item);
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }

    /**
     * @param float|PriceCollection|string|int|null $value
     */
    private function buildDiscountDefinition(string $type, $value, string $key): PriceDefinitionInterface
    {
        if ($type === PercentagePriceDefinition::TYPE) {
            if ($value instanceof PriceCollection) {
                throw CartException::invalidPercentageDiscount($key);
            }
            $value = FloatComparator::cast((float) $value);

            return new PercentagePriceDefinition(abs($value) * -1);
        }
        if ($type !== AbsolutePriceDefinition::TYPE) {
            throw CartException::discountTypeNotSupported($key, $type);
        }
        if (!$value instanceof PriceCollection) {
            throw CartException::absoluteDiscountMissingPriceCollection($key);
        }
        if (!$value->has(Defaults::CURRENCY)) {
            throw CartException::missingDefaultPriceCollectionForDiscount($key);
        }

        foreach ($value as $price) {
            $price->setGross(\abs($price->getGross()) * -1);
            $price->setNet(\abs($price->getNet()) * -1);

            if (!$price->getListPrice()) {
                continue;
            }
            $price->getListPrice()->setGross(\abs($price->getListPrice()->getGross()) * -1);
            $price->getListPrice()->setNet(\abs($price->getListPrice()->getNet()) * -1);
        }

        return new CurrencyPriceDefinition($value);
    }
}
