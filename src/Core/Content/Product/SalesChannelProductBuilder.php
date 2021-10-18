<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelProductBuilder extends AbstractSalesChannelProductBuilder
{
    private AbstractPropertyGroupSorter $propertyGroupSorter;

    private AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator;

    private AbstractIsNewDetector $isNewDetector;

    public function __construct(
        AbstractPropertyGroupSorter $propertyGroupSorter,
        AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        AbstractIsNewDetector $isNewDetector
    ) {
        $this->propertyGroupSorter = $propertyGroupSorter;
        $this->maxPurchaseCalculator = $maxPurchaseCalculator;
        $this->isNewDetector = $isNewDetector;
    }

    public function getDecorated(): AbstractSalesChannelProductBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(SalesChannelProductEntity $product, SalesChannelContext $context): void
    {
        if (($properties = $product->getProperties()) !== null) {
            $product->setSortedProperties(
                $this->propertyGroupSorter->sort($properties)
            );
        }

        $product->setCalculatedMaxPurchase(
            $this->maxPurchaseCalculator->calculate($product, $context)
        );

        $product->setIsNew(
            $this->isNewDetector->isNew($product, $context)
        );
    }
}
