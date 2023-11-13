<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CustomCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly QuantityPriceCalculator $calculator)
    {
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original
            ->getLineItems()
            ->filterFlatByType(LineItem::CUSTOM_LINE_ITEM_TYPE);

        foreach ($lineItems as $lineItem) {
            $this->enrich($lineItem);
        }
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original->getLineItems()->filterType(LineItem::CUSTOM_LINE_ITEM_TYPE);

        foreach ($lineItems as $lineItem) {
            $definition = $lineItem->getPriceDefinition();

            if (!$definition instanceof QuantityPriceDefinition) {
                continue;
            }

            $lineItem->setPrice(
                $this->calculator->calculate(
                    $definition,
                    $context
                )
            );

            $toCalculate->add($lineItem);
        }
    }

    private function enrich(LineItem $lineItem): void
    {
        if ($lineItem->getDeliveryInformation() !== null) {
            return;
        }

        $lineItem->setDeliveryInformation(new DeliveryInformation($lineItem->getQuantity(), 0, false));
    }
}
