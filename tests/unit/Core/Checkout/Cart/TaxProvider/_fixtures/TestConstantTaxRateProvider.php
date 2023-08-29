<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class TestConstantTaxRateProvider extends AbstractTaxProvider
{
    public function __construct(private readonly float $taxRate = 0.07)
    {
    }

    /**
     * Provides a tax provider struct with the calculated taxes corresponding to the $taxRate
     */
    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $lineItems = [];

        foreach ($cart->getLineItems() as $lineItem) {
            if (!$lineItem->getPrice() instanceof CalculatedPrice) {
                continue;
            }

            $lineItems[$lineItem->getUniqueIdentifier()] = new CalculatedTaxCollection([
                new CalculatedTax(
                    $lineItem->getPrice()->getTotalPrice() * $this->taxRate,
                    $this->taxRate * 100,
                    $lineItem->getPrice()->getTotalPrice()
                ),
            ]);
        }

        $deliveries = [];

        foreach ($cart->getDeliveries() as $delivery) {
            if ($delivery->getShippingCosts()->getTotalPrice() === 0.0) {
                continue;
            }

            $position = $delivery->getPositions()->first();

            if (!$position instanceof DeliveryPosition) {
                continue;
            }

            /** @var string $positionId */
            $positionId = $position->getIdentifier();

            $deliveries[$positionId] = new CalculatedTaxCollection([
                new CalculatedTax(
                    $delivery->getShippingCosts()->getTotalPrice() * $this->taxRate,
                    $this->taxRate * 100,
                    $delivery->getShippingCosts()->getTotalPrice()
                ),
            ]);
        }

        $lineItemPrices = $cart->getLineItems()->getPrices();
        $shippingPrices = $cart->getDeliveries()->getShippingCosts();

        $total = $lineItemPrices->merge($shippingPrices)->sum();

        if ($cart->getLineItems()->count() > 0 || $cart->getDeliveries()->count() > 0) {
            $cartTax = new CalculatedTaxCollection([
                new CalculatedTax(
                    $total->getTotalPrice() * $this->taxRate,
                    $this->taxRate * 100,
                    $total->getTotalPrice()
                ),
            ]);
        }

        return new TaxProviderResult($lineItems, $deliveries, $cartTax ?? null);
    }
}
