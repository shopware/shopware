<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelProductBuilder extends AbstractSalesChannelProductBuilder
{
    private SystemConfigService $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    ) {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractSalesChannelProductBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(SalesChannelProductEntity $product, SalesChannelContext $context): void
    {
        $product->setSortedProperties(
            $this->sortProperties($product)
        );

        $product->setCalculatedMaxPurchase(
            $this->calculateMaxPurchase($product, $context)
        );

        $product->setIsNew(
            $this->isProductNew($product, $context)
        );
    }

    private function sortProperties(SalesChannelProductEntity $product): PropertyGroupCollection
    {
        $properties = $product->getProperties();
        if ($properties === null) {
            return new PropertyGroupCollection();
        }

        $sorted = [];
        foreach ($properties as $option) {
            $origin = $option->getGroup();

            if (!$origin || !$origin->getVisibleOnProductDetailPage()) {
                continue;
            }
            $group = clone $origin;

            $groupId = $group->getId();
            if (\array_key_exists($groupId, $sorted)) {
                \assert($sorted[$groupId]->getOptions() !== null);
                $sorted[$groupId]->getOptions()->add($option);

                continue;
            }

            if ($group->getOptions() === null) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            \assert($group->getOptions() !== null);
            $group->getOptions()->add($option);

            $sorted[$groupId] = $group;
        }

        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig();

        return $collection;
    }

    private function calculateMaxPurchase(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): int {
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

    private function isProductNew(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): bool {
        $markAsNewDayRange = $this->systemConfigService->get(
            'core.listing.markAsNew',
            $context->getSalesChannel()->getId()
        );

        $now = new \DateTime();

        return $product->getReleaseDate() instanceof \DateTimeInterface
            && $product->getReleaseDate()->diff($now)->days <= $markAsNewDayRange;
    }
}
