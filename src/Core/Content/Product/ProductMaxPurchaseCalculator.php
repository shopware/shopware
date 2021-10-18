<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductMaxPurchaseCalculator extends AbstractProductMaxPurchaseCalculator
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractProductMaxPurchaseCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    public function calculate(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $fallback = $this->systemConfigService->getInt(
            'core.cart.maxQuantity',
            $context->getSalesChannel()->getId()
        );

        $max = $product->getMaxPurchase() ?? $fallback;

        if ($product->getIsCloseout() && $product->getAvailableStock() < $max) {
            $max = (int) $product->getAvailableStock();
        }

        $steps = $product->getPurchaseSteps() ?? 1;
        $min = $product->getMinPurchase() ?? 1;

        // the amount of times the purchase step is fitting in between min and max added to the minimum
        $max = \floor(($max - $min) / $steps) * $steps + $min;

        return (int) \max($max, 0);
    }
}
