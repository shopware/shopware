<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Processor;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\CurrencyPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class ContainerCartProcessor implements CartProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PercentagePriceCalculator $percentageCalculator,
        private readonly QuantityPriceCalculator $quantityCalculator,
        private readonly CurrencyPriceCalculator $currencyCalculator
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $items = $original->getLineItems()->filterFlatByType(LineItem::CONTAINER_LINE_ITEM);
        foreach ($items as $item) {
            if ($item->getChildren()->count() <= 0) {
                $original->remove($item->getId());
            }
        }

        $items = $original->getLineItems()->filterType(LineItem::CONTAINER_LINE_ITEM);
        foreach ($items as $item) {
            $this->calculate($item, $context, $toCalculate->getLineItems());
            $toCalculate->add($item);
        }
    }

    private function calculateCollection(LineItemCollection $items, SalesChannelContext $context, \Closure $condition): void
    {
        foreach ($items as $item) {
            $match = $condition($item);

            if (!$match) {
                continue;
            }

            $this->calculate($item, $context, $items);
        }
    }

    private function calculate(LineItem $item, SalesChannelContext $context, LineItemCollection $scope): void
    {
        if ($item->getChildren()->count() > 0) {
            // we need to calculate the children in a specific order.
            // we can only calculate "referring" price (discount, surcharges) after calculating items with fix prices (products, etc)
            $this->calculateCollection($item->getChildren(), $context, fn (LineItem $item) => $item->getChildren()->count() > 0);

            $this->calculateCollection($item->getChildren(), $context, fn (LineItem $item) => $item->getPriceDefinition() instanceof QuantityPriceDefinition);

            $this->calculateCollection($item->getChildren(), $context, fn (LineItem $item) => $item->getPriceDefinition() instanceof CurrencyPriceDefinition);

            $this->calculateCollection($item->getChildren(), $context, fn (LineItem $item) => $item->getPriceDefinition() instanceof PercentagePriceDefinition);

            if (!$this->validate($item)) {
                $scope->remove($item->getId());

                return;
            }

            $item->setPrice(
                $item->getChildren()->getPrices()->sum()
            );

            return;
        }

        $definition = $item->getPriceDefinition();

        if ($definition instanceof PercentagePriceDefinition) {
            $price = $this->percentageCalculator->calculate($definition->getPercentage(), $scope->filterGoods()->getPrices(), $context);
        } elseif ($definition instanceof CurrencyPriceDefinition) {
            $price = $this->currencyCalculator->calculate($definition->getPrice(), $scope->filterGoods()->getPrices(), $context);
        } elseif ($definition instanceof QuantityPriceDefinition) {
            $price = $this->quantityCalculator->calculate($definition, $context);
        } else {
            throw CartException::missingLineItemPrice($item->getId());
        }

        $item->setPrice($price);
    }

    private function validate(LineItem $item): bool
    {
        foreach ($item->getChildren() as $child) {
            if ($child->getPrice() === null) {
                return false;
            }

            // absolute price definition are not supported here, use CurrencyPriceDefinition instead
            if ($child->getPriceDefinition() instanceof AbsolutePriceDefinition) {
                return false;
            }
        }

        $total = $item->getChildren()->getPrices()->sum()->getTotalPrice();

        if (FloatComparator::lessThan($total, 0)) {
            return false;
        }

        return true;
    }
}
