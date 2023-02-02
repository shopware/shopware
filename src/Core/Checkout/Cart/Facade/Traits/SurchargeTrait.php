<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

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
trait SurchargeTrait
{
    private LineItemCollection $items;

    /**
     * The `surcharge()` methods creates a new surcharge line-item with the given type and value.
     *
     * @param string $key The id for the new surcharge.
     * @param string $type The type of the surcharge, e.g. `percentage`, `absolute`
     * @param float|PriceCollection $value The value of the surcharge, a float for percentage surcharges or a `PriceCollection` for absolute surcharges.
     * @param string $label The label of the surcharge line-item.
     *
     * @return DiscountFacade Returns the newly created surcharge line-item.
     *
     * @example add-absolute-surcharge/add-absolute-surcharge.twig Add an absolute surcharge to the cart.#
     * @example add-simple-surcharge/add-simple-surcharge.twig Add a relative surcharge to the cart.
     */
    public function surcharge(string $key, string $type, float|PriceCollection $value, string $label): DiscountFacade
    {
        $definition = $this->buildSurchargeDefinition($type, $value, $key);

        $item = new LineItem($key, LineItem::DISCOUNT_LINE_ITEM, null, 1);
        $item->setGood(false);
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

    private function buildSurchargeDefinition(string $type, float|PriceCollection|string|int $value, string $key): PriceDefinitionInterface
    {
        if ($type === PercentagePriceDefinition::TYPE) {
            if ($value instanceof PriceCollection) {
                throw new \RuntimeException('Percentage discounts requires a provided float value');
            }

            $value = FloatComparator::cast((float) $value);

            return new PercentagePriceDefinition(abs($value));
        }
        if ($type !== AbsolutePriceDefinition::TYPE) {
            throw new \InvalidArgumentException(sprintf('Discount type %s not supported', $type));
        }
        if (!$value instanceof PriceCollection) {
            throw new \RuntimeException(sprintf('Absolute discounts %s requires a provided price collection. Use services.price(...) to create a price', $key));
        }
        if (!$value->has(Defaults::CURRENCY)) {
            throw new \RuntimeException(sprintf('Absolute discounts %s requires a defined currency price for the default currency. Use services.price(...) to create a compatible price object', $key));
        }

        foreach ($value as $price) {
            $price->setGross(\abs($price->getGross()));
            $price->setNet(\abs($price->getNet()));

            if (!$price->getListPrice()) {
                continue;
            }
            $price->getListPrice()->setGross(\abs($price->getListPrice()->getGross()));
            $price->getListPrice()->setNet(\abs($price->getListPrice()->getNet()));
        }

        return new CurrencyPriceDefinition($value);
    }
}
