<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupBuilder
{
    /**
     * @var LineItemGroupServiceRegistry
     */
    private $registry;

    /**
     * @var LineItemGroupRuleMatcherInterface
     */
    private $ruleMatcher;

    public function __construct(LineItemGroupServiceRegistry $registry, LineItemGroupRuleMatcherInterface $ruleMatcher)
    {
        $this->registry = $registry;
        $this->ruleMatcher = $ruleMatcher;
    }

    /**
     * Searches for all packages that can be built from the provided list of groups.
     * Every line item will be taken from the cart and only the ones that are left will
     * be checked for upcoming groups.
     *
     * @throws Exception\LineItemGroupPackagerNotFoundException
     * @throws Exception\LineItemGroupSorterNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function findPackages(LineItemGroupDefinition $groupDefinition, Cart $cart, SalesChannelContext $context): array
    {
        // filter out all promotion items
        /** @var LineItemCollection $restOfCart */
        $restOfCart = new LineItemCollection($cart->getLineItems()->fmap(static function (LineItem $lineItem) {
            if ($lineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
                return null;
            }

            return $lineItem;
        }));

        $foundGroups = [];

        // /** @var PromotionSetGroupEntity $group */
        //  foreach ($groups->getElements() as $group) {
        // repeat until we have no more found results,
        // then continue with the next group
        while (true) {
            /** @var LineItemGroupSorterInterface $sorter */
            $sorter = $this->registry->getSorter($groupDefinition->getSorterKey());

            /** @var LineItemGroupPackagerInterface $packager */
            $packager = $this->registry->getPackager($groupDefinition->getPackagerKey());

            /** @var LineItemCollection $availableItems */
            $availableItems = $this->ruleMatcher->getMatchingItems($groupDefinition, $restOfCart, $context);

            // sort using our found sorter
            $availableItems = $sorter->sort($availableItems);

            // now build a package with our packager
            /** @var LineItemCollection $foundItems */
            $foundItems = $packager->buildGroupPackage($groupDefinition->getValue(), $availableItems, $context);

            // if we have no found items in our group, quit
            if ($foundItems->count() <= 0) {
                break;
            }

            // add a new found group
            $foundGroups[] = new LineItemGroupResult($groupDefinition, $foundItems);

            // decrease rest of cart items for next search
            /** @var LineItemCollection $restOfCart */
            $restOfCart = $this->adjustRestOfCart($foundItems, $restOfCart, $context);
        }

        //   }

        return $foundGroups;
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function adjustRestOfCart(LineItemCollection $takeAwayItems, LineItemCollection $cartItems, SalesChannelContext $context): LineItemCollection
    {
        $newRest = new LineItemCollection([]);

        /** @var LineItem $lineItem */
        foreach ($cartItems as $lineItem) {
            if (!$takeAwayItems->exists($lineItem)) {
                $newRest->add($lineItem);
            }
        }

        return $newRest;
    }
}
