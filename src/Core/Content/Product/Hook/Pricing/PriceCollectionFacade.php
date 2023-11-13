<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Hook\Pricing;

use Shopware\Core\Checkout\Cart\Facade\PriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The PriceCollectionFacade is a wrapper around the calculated price collection of a product. It allows to manipulate the quantity
 * prices by resetting or changing the price collection.
 *
 * @script-service product
 *
 * @implements \IteratorAggregate<PriceFacade>
 */
#[Package('inventory')]
class PriceCollectionFacade implements \IteratorAggregate, \Countable
{
    public function __construct(
        private readonly Entity $product,
        private readonly CalculatedPriceCollection $prices,
        private readonly ScriptPriceStubs $priceStubs,
        private readonly SalesChannelContext $context
    ) {
    }

    /**
     * The `reset()` functions allows to reset the complete price collection.
     */
    public function reset(): void
    {
        $this->prices->clear();
    }

    /**
     * The `change()` function allows a complete overwrite of the product quantity prices
     *
     * @param array{to: int|null, price: PriceCollection}[] $changes
     *
     * @example pricing-cases/product-pricing.twig 40 5 Overwrite the product prices with a new quantity price graduation
     */
    public function change(array $changes): void
    {
        $mapped = [];
        foreach ($changes as $change) {
            $mapped[(string) $change['to']] = $change['price'];
        }

        // check for "null" value
        if (!\array_key_exists('', $mapped)) {
            throw ProductException::invalidPriceDefinition();
        }

        $last = $mapped[null];
        unset($mapped[null]);

        \ksort($mapped, \SORT_NUMERIC);
        $max = \max(\array_keys($mapped));

        $mapped[$max + 1] = $last;

        $this->prices->clear();

        $rules = $this->context->buildTaxRules($this->product->get('taxId'));

        foreach ($mapped as $quantity => $price) {
            $value = $this->getPriceForTaxState($price, $this->context);

            $definition = new QuantityPriceDefinition($value, $rules, $quantity);

            $this->prices->add(
                $this->priceStubs->calculateQuantity($definition, $this->context)
            );
        }
    }

    /**
     * @internal
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(
            $this->prices->map(function (CalculatedPrice $price) {
                return new PriceFacade($this->product, $price, $this->priceStubs, $this->context);
            })
        );
    }

    /**
     * The `count()` function returns the number of prices which are stored inside this collection.
     *
     * @return int Returns the number of prices which are stored inside this collection
     */
    public function count(): int
    {
        return $this->prices->count();
    }

    private function getPriceForTaxState(PriceCollection $price, SalesChannelContext $context): float
    {
        $currency = $price->getCurrencyPrice($this->context->getCurrencyId());

        if (!$currency instanceof Price) {
            throw ProductException::invalidPriceDefinition();
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $currency->getGross();
        }

        return $currency->getNet();
    }
}
