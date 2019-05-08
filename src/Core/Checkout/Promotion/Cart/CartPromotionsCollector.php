<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Collector\LineItemCollector;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionGatewayInterface;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartPromotionsCollector implements CollectorInterface
{
    public const DATA_KEY = 'promotions';
    public const LINE_ITEM_TYPE = 'promotion';

    /**
     * @var PromotionGatewayInterface
     */
    private $promotionGateway;

    /**
     * @var PromotionItemBuilder
     */
    private $itemBuilder;

    /**
     * @var LineItemCollector
     */
    private $itemCollector;

    public function __construct(PromotionGatewayInterface $promotionGateway)
    {
        $this->promotionGateway = $promotionGateway;
        $this->itemBuilder = new PromotionItemBuilder(self::LINE_ITEM_TYPE);
        $this->itemCollector = new LineItemCollector(self::LINE_ITEM_TYPE);
    }

    /**
     * This function extracts all placeholder line items, as well as promotion-discount line items, that have a
     * promotion code. Later, the codes will be loaded along with all automatic non-code promotions when collection
     * all eligible promotions
     */
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $placeholderItemIds = [];

        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        // We must not touch any existing items!
        // This loops searches for promotion line items (both placeholders and real line items) that have a
        // promotion code
        /** @var LineItem $lineItem */
        foreach ($promotionLineItems as $lineItem) {
            if ($this->isPromotionLineItem($lineItem) && $lineItem->hasPayloadValue('code')) {
                $placeholderItemIds[] = $lineItem->getKey();
            }
        }

        $definitions->add(new CartPromotionsFetchDefinition($placeholderItemIds));
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all promotions with codes from placeholders and existing promotion line items
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be passed on to the enrichment function.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $customer = $context->getCustomer();

        /** @var Collection $promotionDefinitions */
        $promotionDefinitions = $fetchDefinitions->filterInstance(CartPromotionsFetchDefinition::class);

        // verify if we even have to collect data.
        // if no definition object exists (not prepared), then simply skip this step.
        if ($promotionDefinitions->count() <= 0) {
            return;
        }

        /** @var CartPromotionsFetchDefinition $definition */
        $definition = $promotionDefinitions->getElements()[0];

        /** @var array $codes */
        $codes = $this->getCodesFromLineItems($cart, $definition->getLineItemIds());

        /** @var array $promotions */
        $promotions = $this->searchPromotions($codes, $context);

        $newPromotions = [];

        // we now have a list of promotions that could be added to our cart.
        // verify if they have any discounts. if so, add them to our
        // data struct, which ensures that they will be added later in the enrichment process.
        /** @var PromotionEntity $promotion */
        foreach ($promotions as $promotion) {
            if (!$promotion->isOrderCountValid()) {
                continue;
            }

            if ($customer && !$promotion->isOrderCountPerCustomerCountValid($customer->getId())) {
                continue;
            }

            /** @var PromotionDiscountCollection|null $collection */
            $collection = $promotion->getDiscounts();

            // check if no discounts have been set
            if (!$collection instanceof PromotionDiscountCollection || count($collection->getElements()) <= 0) {
                continue;
            }

            $newPromotions[] = $promotion;
        }

        // add the collected promotions to our definition.
        // these promotions are converted into line items in the enrichment function.
        $data->set(self::DATA_KEY, new CartPromotionsDataDefinition($newPromotions));
    }

    /**
     * This function enriches the cart with custom data that has been collected in our previous function.
     * All collected promotions will now be converted into real Promotion Line Items.
     * Placeholder items will be removed accordingly.
     * If we are in "live checkout mode", then we also remove existing promotion line items,
     * that might not be valid anymore.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     */
    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        // first thing is, to remove all promotion placeholder items.
        // the real line items will be added later in here
        //  we only touch new ones, which means, we have to remove their placeholders (only)
        /** @var LineItem $lineItem */
        foreach ($promotionLineItems as $lineItem) {
            if ($this->isPromotionPlaceholder($lineItem)) {
                $cart->getLineItems()->removeElement($lineItem);
            }
        }

        if (!$behavior->isRecalculation()) {
            // we are in "live checkout mode", which means, we also have to ensure
            // that promotions get removed again if the condition isn't met anymore.
            // this could be due to removing a minimum quantity of a product, or anything else.
            // so we remove every item and re-add our valid promotions from the "collect" step again.
            /** @var LineItem $lineItem */
            foreach ($promotionLineItems as $lineItem) {
                if ($this->isRealPromotionItem($lineItem)) {
                    $cart->getLineItems()->removeElement($lineItem);
                }
            }
        }

        // do not add new promotions if we didn't collect data!
        // we have already cleaned up our cart depending on our recalculation mode above.
        // if we didn't collect anything, this is the place to stop enrichment
        if (!$data->has(self::DATA_KEY) && !$data->get(self::DATA_KEY) instanceof CartPromotionsDataDefinition) {
            return;
        }

        /** @var CartPromotionsDataDefinition $promotionData */
        $promotionData = $data->get(self::DATA_KEY);

        // now add all collected and
        // valid promotions to the cart
        /** @var PromotionEntity $promotion */
        foreach ($promotionData->getPromotions() as $promotion) {
            // lets build separate line items for each
            // of the available discounts within the current promotion
            /** @var array $lineItems */
            $lineItems = $this->buildDiscountLineItems($promotion, $cart, $context);

            // ...and finally add our new line items to the cart
            $cart->addLineItems(new LineItemCollection($lineItems));
        }
    }

    /**
     * Gets all promotion line items from the cart.
     * This includes placeholders and real, satisfied promotion line items.
     */
    private function getPromotionLineItems(Cart $cart): array
    {
        return array_filter(
            $cart->getLineItems()->getElements(),
            function (LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );
    }

    private function isPromotionLineItem(LineItem $lineItem): bool
    {
        return $lineItem->getType() === self::LINE_ITEM_TYPE;
    }

    /**
     * Gets if the line item is a placeholder and not yet satisfied
     * promotion line item. This is done by verifying if the key
     * starts with our placeholder prefix
     */
    private function isPromotionPlaceholder(LineItem $lineItem): bool
    {
        if (!$this->isPromotionLineItem($lineItem)) {
            return false;
        }

        return substr($lineItem->getKey(), 0, strlen(PromotionItemBuilder::PLACEHOLDER_PREFIX)) === PromotionItemBuilder::PLACEHOLDER_PREFIX;
    }

    /**
     * Gets if the line item is a real and satisfied promotion line item.
     */
    private function isRealPromotionItem(LineItem $lineItem): bool
    {
        if (!$this->isPromotionLineItem($lineItem)) {
            return false;
        }

        // its a correct line item type, so it can either be
        // a placeholder or a real one.
        return !$this->isPromotionPlaceholder($lineItem);
    }

    /**
     * This function extracts all code strings from line items with the provided ids.
     */
    private function getCodesFromLineItems(Cart $cart, array $lineItemIDs): array
    {
        $codes = [];

        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        /** @var LineItem $lineItem */
        foreach ($promotionLineItems as $lineItem) {
            // if our line item is in our list of Ids then collect that code.
            if (!in_array($lineItem->getKey(), $lineItemIDs, true)) {
                continue;
            }

            // grab our code from the payload
            // just verify if it really exists to avoid exceptions
            if (array_key_exists('code', $lineItem->getPayload())) {
                $codes[] = $lineItem->getPayload()['code'];
            }
        }

        return $codes;
    }

    /**
     * Gets a combination of all promotion objects that
     * are valid due to automatic promotions or that have
     * been manually added by using their codes.
     */
    private function searchPromotions(array $codes, SalesChannelContext $context): array
    {
        /* @var PromotionCollection $codePromotions */
        $codePromotions = $this->promotionGateway->getByCodes($codes, $context);

        /** @var PromotionCollection $automaticPromotions */
        $automaticPromotions = $this->promotionGateway->getAutomaticPromotions($context);

        /** @var array $listPromotionsAdded */
        $listPromotionsAdded = $codePromotions->getElements();

        /** @var array $listPromotionsAutomatic */
        $listPromotionsAutomatic = $automaticPromotions->getElements();

        return array_merge($listPromotionsAdded, $listPromotionsAutomatic);
    }

    /**
     * This function builds separate line items for each of the
     * available discounts within the provided promotion.
     * Every item will be built with a corresponding price definition based
     * on the configuration of a discount entity.
     * The resulting list of line items will then be returned and can
     * be added to the cart.
     * The function will already avoid duplicate entries.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    private function buildDiscountLineItems(PromotionEntity $promotion, Cart $cart, SalesChannelContext $context): array
    {
        /** @var PromotionDiscountCollection|null $collection */
        $collection = $promotion->getDiscounts();

        if (!$collection instanceof PromotionDiscountCollection) {
            return [];
        }

        $lineItems = [];

        /** @var PromotionDiscountEntity $discount */
        foreach ($collection->getElements() as $discount) {
            // skip if already added! we do not update existing items!
            // depending on our recalculation mode, all promotion items have been removed anyway by now.
            // in recalculation mode, we only add NEW items...and not edit existing ones!
            if ($cart->has($discount->getId())) {
                continue;
            }

            $itemIDs = [];

            // check what type of discount we have.
            // with this, we know how we have to apply our discount.
            // either add discount line items with filter rules
            // or reduce shipping costs, and so on...
            switch ($discount->getScope()) {
                case PromotionDiscountEntity::SCOPE_CART:
                    $itemIDs = $this->itemCollector->getAllLineItemIDs($cart);
                    break;
            }

            // add a new discount line item for this discount
            // if we have at least one valid item that will be discounted.
            if (count($itemIDs) > 0) {
                /* @var LineItem $discountItem */
                $discountItem = $this->itemBuilder->buildDiscountLineItem(
                    $promotion,
                    $discount,
                    $context->getContext()->getCurrencyPrecision()
                );

                $lineItems[] = $discountItem;
            }
        }

        return $lineItems;
    }
}
