<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AdvancedPackageRules extends SetGroupScopeFilter
{
    public function getDecorated(): SetGroupScopeFilter
    {
        throw new DecorationPatternException(self::class);
    }

    public function filter(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountPackageCollection
    {
        $filtered = new DiscountPackageCollection();

        foreach ($packages as $package) {
            [$metaData, $cartItems] = $this->filterPackage($package, $discount->getPriceDefinition(), $context);

            if (\count($metaData) > 0) {
                $filtered->add($this->createFilteredValuesPackage($metaData, $cartItems));
            }
        }

        return $filtered;
    }

    private function isRulesFilterValid(LineItem $item, PriceDefinitionInterface $priceDefinition, SalesChannelContext $context): bool
    {
        if (Feature::isActive('v6.8.0.0')) {
            if (!$priceDefinition instanceof FilterableInterface) {
                return true;
            }

            $filter = $priceDefinition->getFilter();
            if ($filter === null) {
                return true;
            }
        } else {
            // if the price definition doesnt allow filters,
            // then return valid for the item
            if (!method_exists($priceDefinition, 'getFilter')) {
                return true;
            }

            /** @var Rule|null $filter */
            $filter = $priceDefinition->getFilter();

            // if the definition exists, but is empty
            // this means we have no restrictions and thus its valid
            if (!$filter instanceof Rule) {
                return true;
            }
        }

        return $filter->match(new LineItemScope($item, $context));
    }

    /**
     * @return array{array<string, LineItemQuantity>, array<string, LineItem>}
     */
    private function filterPackage(DiscountPackage $package, PriceDefinitionInterface $priceDefinition, SalesChannelContext $context): array
    {
        $checkedItems = [];
        $metaData = [];
        $cartItems = [];

        foreach ($package->getMetaData() as $key => $item) {
            $id = $item->getLineItemId();
            if (!\array_key_exists($id, $checkedItems)) {
                $lineItem = $package->getCartItem($id);

                if ($this->isRulesFilterValid($lineItem, $priceDefinition, $context)) {
                    $checkedItems[$id] = $lineItem;
                }
            }

            if (isset($checkedItems[$id])) {
                $metaData[$key] = $item;
                $cartItems[$key] = $checkedItems[$id];
            }
        }

        return [$metaData, $cartItems];
    }

    /**
     * @param array<string, LineItemQuantity> $metaData
     * @param array<string, LineItem> $cartItems
     */
    private function createFilteredValuesPackage(array $metaData, array $cartItems): DiscountPackage
    {
        // assign instead of add for performance reasons
        $metaCollection = new LineItemQuantityCollection();
        $metaCollection->assign(['elements' => $metaData]);

        // assign instead of add for performance reasons
        $cartCollection = new LineItemFlatCollection();
        $cartCollection->assign(['elements' => $cartItems]);

        $package = new DiscountPackage($metaCollection);
        $package->setCartItems($cartCollection);

        return $package;
    }
}
