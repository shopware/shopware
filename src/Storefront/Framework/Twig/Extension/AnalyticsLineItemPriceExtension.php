<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Checkout\Cart\AnalyticsLineItemPriceCalculator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the analytics prices of the line items to the hidden line item markup, see
 * {@see AnalyticsLineItemPriceCalculator}.
 *
 * @internal
 */
#[Package('checkout')]
class AnalyticsLineItemPriceExtension extends AbstractExtension
{
    public function __construct(private readonly AnalyticsLineItemPriceCalculator $calculator)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_analytics_line_item_prices', $this->calculator->calculate(...)),
        ];
    }
}
