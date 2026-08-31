<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('inventory')]
class ProductMaxPurchaseCalculator extends AbstractProductMaxPurchaseCalculator
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractProductMaxPurchaseCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    public function calculate(Entity $product, SalesChannelContext $context): int
    {
        if ($this->isDigitalProduct($product)) {
            return 1;
        }

        $fallback = $this->systemConfigService->getInt(
            'core.cart.maxQuantity',
            $context->getSalesChannelId()
        );

        $max = $product->get('maxPurchase') ?? $fallback;

        if ($product->get('isCloseout') && $product->get('stock') < $max) {
            $max = (int) $product->get('stock');
        }

        $steps = $product->get('purchaseSteps') ?? 1;
        $min = $product->get('minPurchase') ?? 1;

        // the amount of times the purchase step is fitting in between min and max added to the minimum
        $max = \floor(($max - $min) / $steps) * $steps + $min;

        return (int) \max($max, 0);
    }

    private function isDigitalProduct(Entity $product): bool
    {
        if ($product->get('type') === ProductDefinition::TYPE_DIGITAL) {
            return true;
        }

        // v6.7 fallback: type backfill is deferred to updateDestructive (#16282), so also accept the legacy IS_DOWNLOAD state.
        if (Feature::isActive('v6.8.0.0')) {
            return false;
        }

        $states = $product->get('states');

        return \is_array($states) && \in_array(State::IS_DOWNLOAD, $states, true);
    }
}
