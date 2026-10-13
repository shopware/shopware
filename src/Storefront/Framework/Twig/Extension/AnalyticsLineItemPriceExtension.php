<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Analytics\AbstractAnalyticsLineItemPriceCalculator;
use Shopware\Storefront\Framework\Analytics\AnalyticsLineItemPriceCollection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the analytics prices of the line items to the hidden line item markup, see
 * {@see AbstractAnalyticsLineItemPriceCalculator}.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsLineItemPriceExtension extends AbstractExtension
{
    public function __construct(private readonly AbstractAnalyticsLineItemPriceCalculator $calculator)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_analytics_line_item_prices', $this->getPrices(...)),
        ];
    }

    /**
     * The line items come from a template, which does not guarantee their type, so anything that is
     * not a cart or order line item is dropped before the calculator sees it.
     */
    public function getPrices(mixed $lineItems, SalesChannelContext $context): AnalyticsLineItemPriceCollection
    {
        if (!is_iterable($lineItems)) {
            return $this->calculator->calculate(null, $context);
        }

        $filtered = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem instanceof LineItem || $lineItem instanceof OrderLineItemEntity) {
                $filtered[] = $lineItem;
            }
        }

        return $this->calculator->calculate($filtered, $context);
    }
}
