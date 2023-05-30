<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedAutomaticPromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedGlobalCodePromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedIndividualCodePromotions;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionCollector implements CartDataCollectorInterface
{
    use PromotionCartInformationTrait;

    final public const SKIP_PROMOTION = 'skipPromotion';
    final public const SKIP_AUTOMATIC_PROMOTIONS = 'skipAutomaticPromotions';

    /**
     * @var string[]
     */
    private readonly array $requiredDalAssociations;

    /**
     * @internal
     */
    public function __construct(
        private readonly PromotionGatewayInterface $gateway,
        private readonly PromotionItemBuilder $itemBuilder,
        private readonly HtmlSanitizer $htmlSanitizer
    ) {
        $this->requiredDalAssociations = [
            'personaRules',
            'personaCustomers',
            'cartRules',
            'orderRules',
            'discounts.discountRules',
            'discounts.promotionDiscountPrices',
            'setgroups',
            'setgroups.setGroupRules',
        ];
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all promotions with codes within our cart extension
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be used in the enrichment process and converted
     * into Line Items which will be passed on to the next processor.
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     * @throws InconsistentCriteriaIdsException
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::promotion::collect', function () use ($data, $original, $context, $behavior): void {
            // The promotions have a special function:
            // If the user comes to the shop via a promotion link, a discount is to be placed in the cart.
            // However, this cannot be applied directly, because it does not yet have any items in the cart.
            // Therefore the code is stored in the extension and as soon
            // as the user has enough items in the cart, it is added again.
            $cartExtension = $original->getExtension(CartExtension::KEY);
            if (!$cartExtension instanceof CartExtension) {
                $cartExtension = new CartExtension();
                $original->addExtension(CartExtension::KEY, $cartExtension);
            }

            // if we are in recalculation,
            // we must not re-add any promotions. just leave it as it is.
            if ($behavior->hasPermission(self::SKIP_PROMOTION)) {
                return;
            }

            // now get the codes from our configuration
            // and also from our line items (that already exist)
            // and merge them both into a flat list
            $extensionCodes = $cartExtension->getCodes();
            $cartCodes = $original->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->getReferenceIds();
            $allCodes = array_unique(array_merge(array_values($cartCodes), $extensionCodes));

            $allPromotions = $this->searchPromotionsByCodes($data, $allCodes, $context);

            if (!$behavior->hasPermission(self::SKIP_AUTOMATIC_PROMOTIONS)) {
                // add auto promotions
                $allPromotions->addAutomaticPromotions($this->searchPromotionsAuto($data, $context));
            }

            // check if max allowed redemption of promotion have been reached or not
            // if max redemption has been reached promotion will not be added
            $allPromotions = $this->getEligiblePromotionsWithDiscounts($allPromotions, $context->getCustomer());

            $discountLineItems = [];
            $foundCodes = [];

            /** @var PromotionCodeTuple $tuple */
            foreach ($allPromotions->getPromotionCodeTuples() as $tuple) {
                // verify if the user might have removed and "blocked"
                // the promotion from being added again
                if ($cartExtension->isPromotionBlocked($tuple->getPromotion()->getId())) {
                    continue;
                }

                // lets build separate line items for each
                // of the available discounts within the current promotion
                $lineItems = $this->buildDiscountLineItems($tuple->getCode(), $tuple->getPromotion(), $original, $context);

                // add to our list of all line items
                /** @var LineItem $nested */
                foreach ($lineItems as $nested) {
                    $discountLineItems[] = $nested;
                }

                // we need the list of found codes
                // for our NotFound errors below
                $foundCodes[] = $tuple->getCode();
            }

            // now iterate through all codes that have been added
            // and add errors, if a promotion for that code couldn't be found
            foreach ($allCodes as $code) {
                if (!\in_array($code, $foundCodes, true)) {
                    $cartExtension->removeCode((string) $code);

                    $this->addPromotionNotFoundError($this->htmlSanitizer->sanitize((string) $code, null, true), $original);
                }
            }

            // if we do have promotions, set them to be processed
            // otherwise make sure to remove the entry to avoid any processing
            // within our promotions scope
            if (\count($discountLineItems) > 0) {
                $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection($discountLineItems));
            } else {
                $data->remove(PromotionProcessor::DATA_KEY);
            }
        }, 'cart');
    }

    /**
     * Gets either the cached list of auto-promotions that
     * are valid, or loads them from the database.
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return PromotionEntity[]
     */
    private function searchPromotionsAuto(CartDataCollection $data, SalesChannelContext $context): array
    {
        if ($data->has('promotions-auto')) {
            return $data->get('promotions-auto');
        }

        $criteria = (new Criteria())->addFilter(new PermittedAutomaticPromotions($context->getSalesChannel()->getId()));

        /** @var string $association */
        foreach ($this->requiredDalAssociations as $association) {
            $criteria->addAssociation($association);
        }

        /** @var PromotionCollection $automaticPromotions */
        $automaticPromotions = $this->gateway->get($criteria, $context);

        $data->set('promotions-auto', $automaticPromotions->getElements());

        return $automaticPromotions->getElements();
    }

    /**
     * Gets all promotions by using the provided list of codes.
     * The promotions will be either taken from a cached list of a previous call,
     * or are loaded directly from the database if a certain code is new
     * and has not yet been fetched.
     *
     * @param array<mixed> $allCodes
     *
     * @throws InconsistentCriteriaIdsException
     */
    private function searchPromotionsByCodes(CartDataCollection $data, array $allCodes, SalesChannelContext $context): CartPromotionsDataDefinition
    {
        $keyCacheList = 'promotions-code';

        // create a new cached list that is empty at first
        if (!$data->has($keyCacheList)) {
            $data->set($keyCacheList, new CartPromotionsDataDefinition());
        }

        // load it
        /** @var CartPromotionsDataDefinition $promotionsList */
        $promotionsList = $data->get($keyCacheList);

        // our data is a runtime cached structure.
        // but when line items get removed, the collect function gets called multiple times.
        // in the first iterations we still have a promotion code item
        // and then it is suddenly gone. so we also have to remove
        // entities from our cache if the code is suddenly not provided anymore.
        /*
         * @var string
         */
        foreach ($promotionsList->getAllCodes() as $code) {
            // if code is not existing anymore,
            // make sure to remove it in our list
            if (!\in_array($code, $allCodes, true)) {
                $promotionsList->removeCode((string) $code);
            }
        }

        $codesToFetch = [];

        // let's find out what promotions we
        // really need to fetch from our database.

        foreach ($allCodes as $code) {
            // check if promotion is already cached
            if ($promotionsList->hasCode($code)) {
                continue;
            }

            // fetch that new code
            $codesToFetch[] = $code;

            // add a new entry with null
            // so if we cant fetch it, we do at least
            // tell our cache that we have tried it
            $promotionsList->addCodePromotions($code, []);
        }

        // if we have new codes to fetch
        // make sure to load it and assign it to
        // the code in our cache list.
        if (\count($codesToFetch) > 0) {
            $salesChannelId = $context->getSalesChannel()->getId();

            foreach ($codesToFetch as $currentCode) {
                // try to find a global code first because
                // that search has less data involved
                $globalCriteria = (new Criteria())->addFilter(new PermittedGlobalCodePromotions([$currentCode], $salesChannelId));

                /** @var string $association */
                foreach ($this->requiredDalAssociations as $association) {
                    $globalCriteria->addAssociation($association);
                }

                /** @var PromotionCollection $foundPromotions */
                $foundPromotions = $this->gateway->get($globalCriteria, $context);

                if (\count($foundPromotions->getElements()) <= 0) {
                    // no global code, so try with an individual code instead
                    $individualCriteria = (new Criteria())->addFilter(new PermittedIndividualCodePromotions([$currentCode], $salesChannelId));

                    /** @var string $association */
                    foreach ($this->requiredDalAssociations as $association) {
                        $individualCriteria->addAssociation($association);
                    }

                    /** @var PromotionCollection $foundPromotions */
                    $foundPromotions = $this->gateway->get($individualCriteria, $context);
                }

                // if we finally have found promotions add them to our list for the current code
                if (\count($foundPromotions->getElements()) > 0) {
                    $promotionsList->addCodePromotions($currentCode, $foundPromotions->getElements());
                }
            }
        }

        // update our cached list with the latest cleaned array
        $data->set($keyCacheList, $promotionsList);

        return $promotionsList;
    }

    /**
     * function returns all promotions that have discounts and that are eligible
     * (function validates that max usage or customer max usage hasn't exceeded)
     */
    private function getEligiblePromotionsWithDiscounts(CartPromotionsDataDefinition $dataDefinition, ?CustomerEntity $customer): CartPromotionsDataDefinition
    {
        $result = new CartPromotionsDataDefinition();

        // we now have a list of promotions that could be added to our cart.
        // verify if they have any discounts. if so, add them to our
        // data struct, which ensures that they will be added later in the enrichment process.
        /** @var PromotionCodeTuple $tuple */
        foreach ($dataDefinition->getPromotionCodeTuples() as $tuple) {
            $promotion = $tuple->getPromotion();

            if (!$promotion->isOrderCountValid()) {
                continue;
            }

            if ($customer !== null && !$promotion->isOrderCountPerCustomerCountValid($customer->getId())) {
                continue;
            }

            // check if no discounts have been set
            if (!$promotion->hasDiscount()) {
                continue;
            }

            // now add it to our result definition object.
            // we also have to remember the code that has been
            // used for a particular promotion (if promotion is type of code).
            // that's why we differ between automatic and code
            if (empty($tuple->getCode())) {
                $result->addAutomaticPromotions([$promotion]);
            } else {
                $result->addCodePromotions($tuple->getCode(), [$promotion]);
            }
        }

        return $result;
    }

    /**
     * This function builds separate line items for each of the
     * available discounts within the provided promotion.
     * Every item will be built with a corresponding price definition based
     * on the configuration of a discount entity.
     * The resulting list of line items will then be returned and can be added to the cart.
     * The function will already avoid duplicate entries.
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     *
     * @return array<LineItem>
     */
    private function buildDiscountLineItems(string $code, PromotionEntity $promotion, Cart $cart, SalesChannelContext $context): array
    {
        $collection = $promotion->getDiscounts();

        if (!$collection instanceof PromotionDiscountCollection) {
            return [];
        }

        $lineItems = [];

        foreach ($collection->getElements() as $discount) {
            $itemIds = $this->getAllLineItemIds($cart);

            // add a new discount line item for this discount
            // if we have at least one valid item that will be discounted.
            if (\count($itemIds) <= 0) {
                continue;
            }

            $factor = 1.0;
            if (!$context->getCurrency()->getIsSystemDefault()) {
                $factor = $context->getCurrency()->getFactor();
            }

            $discountItem = $this->itemBuilder->buildDiscountLineItem(
                $code,
                $promotion,
                $discount,
                $context->getCurrency()->getId(),
                $factor
            );

            $originalCodeItem = $cart->getLineItems()->filter(function (LineItem $item) use ($code) {
                if ($item->getReferencedId() === $code) {
                    return $item;
                }

                return null;
            })->first();

            if ($originalCodeItem && (is_countable($originalCodeItem->getExtensions()) ? \count($originalCodeItem->getExtensions()) : 0) > 0) {
                $discountItem->setExtensions($originalCodeItem->getExtensions());
            }

            $lineItems[] = $discountItem;
        }

        return $lineItems;
    }

    /**
     * @return array<string>
     */
    private function getAllLineItemIds(Cart $cart): array
    {
        return $cart->getLineItems()->fmap(
            static function (LineItem $lineItem) {
                if ($lineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
                    return null;
                }

                return $lineItem->getId();
            }
        );
    }
}
