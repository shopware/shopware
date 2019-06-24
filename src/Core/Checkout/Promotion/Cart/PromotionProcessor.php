<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionGatewayInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public const DATA_KEY = 'promotions';
    public const LINE_ITEM_TYPE = 'promotion';

    /**
     * @var PromotionCalculator
     */
    private $promotionCalculator;

    /**
     * @var PromotionGatewayInterface
     */
    private $gateway;

    /**
     * @var PromotionItemBuilder
     */
    private $itemBuilder;

    public function __construct(PromotionCalculator $promotionCalculator, PromotionGatewayInterface $gateway, PromotionItemBuilder $itemBuilder)
    {
        $this->promotionCalculator = $promotionCalculator;
        $this->itemBuilder = $itemBuilder;
        $this->gateway = $gateway;
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all promotions with codes from placeholders and existing promotion line items
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be passed on to the enrichment function.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var array $autoPromotions */
        $autoPromotions = $this->searchPromotionsAuto($data, $context);

        /** @var array $allCodes */
        $allCodes = $original
            ->getLineItems()
            ->filterType(self::LINE_ITEM_TYPE)
            ->getReferenceIds();

        /** @var array $codePromotions */
        $codePromotions = $this->searchPromotionsByCodes($data, $allCodes, $context);

        /** @var array $allPromotions */
        $allPromotions = array_merge($autoPromotions, $codePromotions);

        if (count($allPromotions) === 0) {
            return;
        }

        // check if max allowed redemption of promotion have been reached or not
        // if max redemption has been reached promotion will not be added
        /** @var PromotionEntity[] $eligiblePromotions */
        $eligiblePromotions = $this->getEligiblePromotionsWithDiscounts(
            $allPromotions,
            $context->getCustomer()
        );

        // if we do have promotions, set them to be processed
        // otherwise make sure to remove the entry to avoid any processing
        // within our promotions scope
        if (count($eligiblePromotions) >= 0) {
            $data->set(self::DATA_KEY, new PromotionCollection($eligiblePromotions));
        } else {
            $data->remove(self::DATA_KEY);
        }
    }

    /**
     * This function enriches the cart with custom data that has been collected in our previous function.
     * All collected promotions will now be converted into real Promotion Line Items by using our
     * Calculator which validates and fixes our line items and then recalculates the cart after applying promotions.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // if there is no collected promotion we may return - nothing to calculate!
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        // if we are in recalculation,
        // we must not re-add any promotions. just leave it as it is.
        if ($behavior->isRecalculation()) {
            return;
        }

        $discountLineItems = [];

        // get all promotions that have been collected
        // and prepare them for calculating process
        /** @var PromotionCollection $promotionDefinition */
        $promotionDefinition = $data->get(self::DATA_KEY);

        /** @var PromotionEntity $promotion */
        foreach ($promotionDefinition as $promotion) {
            // lets build separate line items for each
            // of the available discounts within the current promotion
            /** @var array $lineItems */
            $lineItems = $this->buildDiscountLineItems($promotion, $calculated, $context);

            // add to our list of all line items
            // that should be added
            foreach ($lineItems as $nested) {
                $discountLineItems[] = $nested;
            }
        }

        // calculate the whole cart with the
        // new list of created promotion discount line items
        $this->promotionCalculator->calculate(
            new LineItemCollection($discountLineItems),
            $original,
            $calculated,
            $context,
            $behavior
        );
    }

    /**
     * Gets either the cached list of auto-promotions that
     * are valid, or loads them from the database.
     */
    private function searchPromotionsAuto(CartDataCollection $data, SalesChannelContext $context): array
    {
        if ($data->has('promotions-auto')) {
            return $data->get('promotions-auto');
        }

        /** @var PromotionCollection $automaticPromotions */
        $automaticPromotions = $this->gateway->getAutomaticPromotions($context);

        $data->set('promotions-auto', $automaticPromotions->getElements());

        return $automaticPromotions->getElements();
    }

    /**
     * Gets all promotions by using the provided list of codes.
     * The promotions will be either taken from a cached list of a previous call,
     * or are loaded directly from the database if a certain code is new
     * and has not yet been fetched.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function searchPromotionsByCodes(CartDataCollection $data, array $allCodes, SalesChannelContext $context): array
    {
        $keyPrefixProcessedCode = 'promotions-code-processed-';
        $keyCacheList = 'promotions-code';

        // create a new cached list that is empty at first
        if (!$data->has($keyCacheList)) {
            $data->set($keyCacheList, []);
        }

        $newCodes = [];

        // let's find out what promotions we
        // really need to fetch from our database.
        /* @var string $code */
        foreach ($allCodes as $code) {
            $key = $keyPrefixProcessedCode . $code;

            if ($data->has($key)) {
                continue;
            }

            $newCodes[] = $code;
            $data->set($key, null);
        }

        if (count($newCodes) <= 0) {
            return $data->get($keyCacheList);
        }

        /* @var PromotionCollection $newPromotions */
        $newPromotions = $this->gateway->getByCodes($newCodes, $context);

        /** @var array $newPromotionsArray */
        $newPromotionsArray = $newPromotions->getElements();

        // add our new promotions to the cache for upcoming calls.
        /** @var array $existingPromotions */
        $existingPromotions = $data->get($keyCacheList);
        $existingPromotions = array_merge($existingPromotions, $newPromotionsArray);
        $data->set($keyCacheList, $existingPromotions);

        return $existingPromotions;
    }

    /**
     * function returns all promotions that have discounts and that are eligible
     * (function validates that max usage or customer max usage hasn't exceeded)
     */
    private function getEligiblePromotionsWithDiscounts(array $promotions, ?CustomerEntity $customer): array
    {
        $eligiblePromotions = [];

        /** @var PromotionEntity $promotion */
        foreach ($promotions as $promotion) {
            if (!$promotion->isOrderCountValid()) {
                continue;
            }

            if ($customer !== null && !$promotion->isOrderCountPerCustomerCountValid($customer->getId())) {
                continue;
            }

            /** @var PromotionDiscountCollection|null $collection */
            $collection = $promotion->getDiscounts();

            // check if no discounts have been set
            if (!$collection instanceof PromotionDiscountCollection || count($collection->getElements()) <= 0) {
                continue;
            }

            $eligiblePromotions[] = $promotion;
        }

        return $eligiblePromotions;
    }

    /**
     * This function builds separate line items for each of the
     * available discounts within the provided promotion.
     * Every item will be built with a corresponding price definition based
     * on the configuration of a discount entity.
     * The resulting list of line items will then be returned and can
     * be added to the cart.
     * The function will already avoid duplicate entries.
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
            // we only calculate discounts with scope cart in this processor
            if ($discount->getScope() !== PromotionDiscountEntity::SCOPE_CART) {
                continue;
            }
            // skip if already added! we do not update existing items!
            // depending on our recalculation mode, all promotion items have been removed anyway by now.
            // in recalculation mode, we only add NEW items...and not edit existing ones!
            if ($cart->has($discount->getId())) {
                continue;
            }

            /** @var array $itemIds */
            $itemIds = $this->getAllLineItemIds($cart);

            // add a new discount line item for this discount
            // if we have at least one valid item that will be discounted.
            if (count($itemIds) <= 0) {
                continue;
            }

            /* @var LineItem $discountItem */
            $discountItem = $this->itemBuilder->buildDiscountLineItem(
                $promotion,
                $discount,
                $context->getContext()->getCurrencyPrecision()
            );

            $lineItems[] = $discountItem;
        }

        return $lineItems;
    }

    private function getAllLineItemIds(Cart $cart): array
    {
        return $cart->getLineItems()->fmap(
            static function (LineItem $lineItem) {
                if ($lineItem->getType() === self::LINE_ITEM_TYPE) {
                    return null;
                }

                return $lineItem->getId();
            }
        );
    }
}
