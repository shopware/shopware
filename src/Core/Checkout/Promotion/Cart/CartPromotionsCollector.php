<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Validator\LineItemRuleValidator;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionGatewayInterface;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
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
     * @var LineItemRuleValidator
     */
    private $itemValidator;

    /**
     * @var bool
     */
    private $featureFlagUnlocked = false;

    public function __construct(PromotionGatewayInterface $promotionGateway)
    {
        $this->promotionGateway = $promotionGateway;
        $this->itemBuilder = new PromotionItemBuilder(self::LINE_ITEM_TYPE);
        $this->itemValidator = new LineItemRuleValidator(self::LINE_ITEM_TYPE);
    }

    /**
     * Sets if the feature is enabled or disabled from code.
     * This is used to enable the whole collector for unit tests.
     * The function can be removed again after deleting the feature flag.
     */
    public function setFeatureFlagUnlocked(bool $isFeatureFlagUnlocked): void
    {
        $this->featureFlagUnlocked = $isFeatureFlagUnlocked;
    }

    /**
     * This function extracts all place holder promotion items from
     * the cart and makes sure they get converted into real promotion line items later.
     * Only these codes along with all automatic non-code promotions will be
     * loaded later when collecting the eligible promotions.
     */
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if (!$this->isFeatureFlagUnlocked()) {
            return;
        }

        $placeholderItemIds = [];

        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        if ($behavior->isRecalculation()) {
            // if we are in recalculation mode, we must not touch any existing items!
            // we do only search for new promotion placeholders and extract their IDs.
            /** @var LineItem $lineItem */
            foreach ($promotionLineItems as $lineItem) {
                if ($this->isPromotionPlaceholder($lineItem)) {
                    $placeholderItemIds[] = $lineItem->getKey();
                }
            }

            $definitions->add(new CartPromotionsFetchDefinition($placeholderItemIds));

            return;
        }

        // in "live checkout mode", we have to ensure we also REMOVE line items
        // if conditions are not met anymore for a promotion!
        // thus we collect all our existing promotion line items (placeholders and real ones)
        // to re-apply them again if still valid...or remove them later on.
        /** @var LineItem $lineItem */
        foreach ($promotionLineItems as $lineItem) {
            if ($this->isPromotionPlaceholder($lineItem)) {
                $placeholderItemIds[] = $lineItem->getKey();
            } elseif ($this->isRealPromotionItem($lineItem)) {
                $placeholderItemIds[] = $lineItem->getKey();
            }
        }

        $definitions->add(new CartPromotionsFetchDefinition($placeholderItemIds));
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all placeholder promotions and their codes
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be passed on to the enrichment function.
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     */
    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if (!$this->isFeatureFlagUnlocked()) {
            return;
        }

        /** @var Collection $promotionDefinitions */
        $promotionDefinitions = $fetchDefinitions->filterInstance(CartPromotionsFetchDefinition::class);

        // verify if we even have to collect data.
        // if not, then simply skip this step.
        if ($promotionDefinitions->count() <= 0) {
            return;
        }

        /** @var CartPromotionsFetchDefinition $definition */
        $definition = $promotionDefinitions->getElements()[0];

        /** @var array $codes */
        $codes = $this->getCodesFromPlaceholdersIds($cart, $definition->getLineItemIds());

        /** @var array $promotions */
        $promotions = $this->searchPromotions($codes, $context);

        $newPromotions = [];

        // we now have a list of promotions that could be added to our cart.
        // we still need to verify a few things, to be sure they are really valid.
        // if so, add them to our collection list.
        /** @var PromotionEntity $promotion */
        foreach ($promotions as $promotion) {
            if (!$promotion->isPersonaConditionValid($context)) {
                continue;
            }

            if (!$promotion->isScopeValid($context)) {
                continue;
            }

            /** @var string[] $eligibleItemIds */
            $eligibleItemIds = $this->itemValidator->getEligibleItemIds($promotion, $cart, $context);

            // promotions that do not discount any items, make no sense...
            // so skip that one too
            if (count($eligibleItemIds) <= 0) {
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
        if (!$this->isFeatureFlagUnlocked()) {
            return;
        }

        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        if ($behavior->isRecalculation()) {
            // we are in recalculation mode
            // this means we must not remove any already added line items!
            // so we only touch new ones, which means, we have to remove their placeholders (only)
            /** @var LineItem $lineItem */
            foreach ($promotionLineItems as $lineItem) {
                if ($this->isPromotionPlaceholder($lineItem)) {
                    $cart->getLineItems()->removeElement($lineItem);
                }
            }
        } else {
            // we are in "live checkout mode", which means, we also have to ensure
            // that promotions get removed again if the condition isn't met anymore.
            // this could be due to removing a minimum quantity of a product, or anything else.
            // so we remove every item and re-add our valid promotions from the "collect" step again.
            /** @var LineItem $lineItem */
            foreach ($promotionLineItems as $lineItem) {
                if ($this->isPromotionPlaceholder($lineItem) || $this->isRealPromotionItem($lineItem)) {
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
            // skip if already added! we do not update existing items!
            // depending on our recalculation mode, all promotion items have been removed anyway by now.
            // in recalculation mode, we only add NEW items...and not edit existing ones!
            if ($cart->has($promotion->getId())) {
                continue;
            }

            /** @var string[] $eligibleItemIds */
            $eligibleItemIds = $this->itemValidator->getEligibleItemIds($promotion, $cart, $context);

            $lineItem = $this->itemBuilder->buildPromotionItem(
                $promotion,
                $context->getContext()->getCurrencyPrecision(),
                $eligibleItemIds
            );

            $cart->add($lineItem);
        }
    }

    /**
     * Gets if the feature is unlocked. This is due to a problem
     * with the unit test. thus we really activate it in there
     * without any feature flag loading (just doesnt work).
     */
    private function isFeatureFlagUnlocked(): bool
    {
        // check if our flag mechanism works
        // we do this with this way due to problems with unit tests
        if (array_key_exists('next700', FeatureConfig::getAll())) {
            return true;
        }

        // otherwise just return what we have configured
        return $this->featureFlagUnlocked;
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

    /**
     * Gets if the line item is a placeholder and not yet satisfied
     * promotion line item. This is done by verifying if the key
     * starts with our placeholder prefix
     */
    private function isPromotionPlaceholder(LineItem $lineItem): bool
    {
        if ($lineItem->getType() !== self::LINE_ITEM_TYPE) {
            return false;
        }

        return substr($lineItem->getKey(), 0, strlen(PromotionItemBuilder::PLACEHOLDER_PREFIX)) === PromotionItemBuilder::PLACEHOLDER_PREFIX;
    }

    /**
     * Gets if the line item is a real and satisfied promotion line item.
     */
    private function isRealPromotionItem(LineItem $lineItem): bool
    {
        if ($lineItem->getType() !== self::LINE_ITEM_TYPE) {
            return false;
        }

        // its a correct line item type, so it can either be
        // a placeholder or a real one.
        return !$this->isPromotionPlaceholder($lineItem);
    }

    /**
     * This function extracts all code strings from the
     * placeholder items of the provided Ids within the cart.
     */
    private function getCodesFromPlaceholdersIds(Cart $cart, array $lineItemIDs): array
    {
        $codes = [];

        /** @var array $promotionLineItems */
        $promotionLineItems = $this->getPromotionLineItems($cart);

        /** @var LineItem $lineItem */
        foreach ($promotionLineItems as $lineItem) {
            // if our line item is in our list of Ids and
            // if it is a placeholder item, then collect that code.
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
        $automaticPromotions = $this->promotionGateway->getByContext($context);

        /** @var array $listPromotionsAdded */
        $listPromotionsAdded = $codePromotions->getElements();

        /** @var array $listPromotionsAutomatic */
        $listPromotionsAutomatic = $automaticPromotions->getElements();

        return array_merge($listPromotionsAdded, $listPromotionsAutomatic);
    }
}
