<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedAutomaticPromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedGlobalCodePromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedIndividualCodePromotions;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionCollector implements CartDataCollectorInterface
{
    use PromotionCartInformationTrait;

    /**
     * Existing set of promotions will not be changed.
     * Promotions may **not** be recalculated based on their price definition.
     *
     * Takes precedence over {@see PIN_MANUAL_PROMOTIONS} and {@see PIN_MANUAL_PROMOTIONS}.
     *
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_PROMOTION}
     */
    final public const SKIP_PROMOTION = CheckoutPermissions::SKIP_PROMOTION;

    /**
     * Skips the addition of automatic promotion.
     * If {@see PIN_AUTOMATIC_PROMOTIONS} is not set, all existing automatic promotions will be deleted.
     *
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS}
     */
    final public const SKIP_AUTOMATIC_PROMOTIONS = CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS;

    /**
     * Existing set of manual/fixed promotions will not be changed,
     * but new manual/fixed promotions can be added.
     * Promotions may be recalculated based on their price definition.
     *
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::PIN_MANUAL_PROMOTIONS}
     */
    final public const PIN_MANUAL_PROMOTIONS = CheckoutPermissions::PIN_MANUAL_PROMOTIONS;

    /**
     * Existing set of automatic promotions will not be changed.
     * Promotions may be recalculated based on their price definition.
     *
     * Takes precedence over {@see SKIP_AUTOMATIC_PROMOTIONS}.
     *
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS}
     */
    final public const PIN_AUTOMATIC_PROMOTIONS = CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS;

    private const CACHE_KEY_CODE = 'promotions-code';
    private const CACHE_KEY_AUTO = 'promotions-auto';

    private const REQUIRED_DAL_ASSOCIATIONS = [
        'personaRules',
        'personaCustomers',
        'cartRules',
        'orderRules',
        'discounts.discountRules',
        'discounts.promotionDiscountPrices',
        'setgroups.setGroupRules',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly PromotionGatewayInterface $gateway,
        private readonly PromotionItemBuilder $itemBuilder,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly Connection $connection
    ) {
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all promotions with codes within our cart extension
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be used in the enrichment process and converted
     * into Line Items which will be passed on to the next processor.
     *
     * @throws CartException
     * @throws PromotionException
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
            $cartExtension = $original->getExtensionOfType(CartExtension::KEY, CartExtension::class) ?? new CartExtension();
            $original->addExtension(CartExtension::KEY, $cartExtension);

            // if we are in recalculation,
            // we must not re-add any promotions. just leave it as it is.
            if ($behavior->hasPermission(CheckoutPermissions::SKIP_PROMOTION)) {
                return;
            }

            // preload our collected lineItems with pinned ones
            $discountLineItems = $this->getPinnedPromotions($original, $behavior);

            // now get the codes from our configuration
            // and also from our line items (that already exist)
            // and merge them both into a flat list
            $extensionCodes = $cartExtension->getCodes();
            $cartCodes = $original->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->getReferenceIds();
            $allCodes = array_unique(array_merge(array_values($cartCodes), $extensionCodes));

            // Pinned: We want to allow the addition of any promotion by code even if promotions are pinned
            $allPromotions = $this->searchPromotionsByCodes($data, $allCodes, $context);

            if (!$behavior->hasPermission(CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS) && !$behavior->hasPermission(CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS)) {
                $allPromotions->addAutomaticPromotions($this->searchPromotionsAuto($data, $context));
            }

            $currentOrderId = $original->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId();

            $foundCodes = $discountLineItems->fmap(static fn (LineItem $item) => $item->getReferencedId());

            foreach ($allPromotions->getPromotionCodeTuples() as $tuple) {
                if (!$this->isEligible($tuple->getPromotion(), $context->getCustomerId(), $currentOrderId)) {
                    continue;
                }

                if ($cartExtension->isPromotionBlocked($tuple->getPromotion()->getId())) {
                    continue;
                }

                $foundCodes[] = $tuple->getCode();

                // skip adding a discount if we don't have a line item to apply a discount on
                if (!$this->hasLineItemToDiscount($original)) {
                    continue;
                }

                // let's build separate line items for each of the available discounts within the current promotion
                $lineItems = $this->buildDiscountLineItems($tuple->getCode(), $tuple->getPromotion(), $original, $context);
                foreach ($lineItems as $nested) {
                    // do not override pinned promotions
                    if (!$discountLineItems->has($nested->getId())) {
                        $discountLineItems->add($nested);
                    }
                }
            }

            // now iterate through all codes that have been added and add errors for all removed promotions
            foreach (\array_diff($allCodes, \array_unique($foundCodes)) as $code) {
                $cartExtension->removeCode((string) $code);

                $this->addPromotionNotFoundError($this->htmlSanitizer->sanitize((string) $code, null, true), $original);
            }

            // when being in a recalculation, having notifications about the removal of automatic promotion is desired
            // addition notifications are handled as usual in the PromotionCalculator
            /** @deprecated tag:v6.8.0 - `$isRecalculation` will be removed without replacement */
            $isRecalculation = !Feature::isActive('v6.8.0.0') && $behavior->isRecalculation();
            if ($isRecalculation || $behavior->hasPermission(CheckoutPermissions::AUTOMATIC_PROMOTION_DELETION_NOTICES)) {
                $oldPromotions = $original->getLineItems()
                    ->filter(static fn (LineItem $item) => $item->getType() === PromotionProcessor::LINE_ITEM_TYPE && !$item->getReferencedId())
                    ->getElements();
                $newPromotions = $discountLineItems->filter(static fn (LineItem $item) => !$item->getReferencedId())->getElements();

                foreach (\array_diff_key($oldPromotions, $newPromotions) as $removedPromotion) {
                    $this->addPromotionDeletedNotice($original, $original, $removedPromotion);
                }
            }

            // if we do have promotions, set them to be processed
            // otherwise make sure to remove the entry to avoid any processing
            // within our promotions scope
            if ($discountLineItems->count() > 0) {
                $data->set(PromotionProcessor::DATA_KEY, $discountLineItems);
            } else {
                $data->remove(PromotionProcessor::DATA_KEY);
            }
        }, 'cart');
    }

    /**
     * Get a collection of all promotions that should be taken over from the original cart
     */
    private function getPinnedPromotions(Cart $original, CartBehavior $behavior): LineItemCollection
    {
        $promotionLineItems = $original
            ->getLineItems()
            ->filterType(PromotionProcessor::LINE_ITEM_TYPE)
            /** Filter out placeholder line.items. {@see PromotionItemBuilder::buildPlaceholderItem} */
            ->filter(static fn (LineItem $item) => $item->getLabel() !== PromotionItemBuilder::PLACEHOLDER_PREFIX . ((string) $item->getReferencedId()));

        $discountLineItems = new LineItemCollection();

        if ($behavior->hasPermission(self::PIN_MANUAL_PROMOTIONS)) {
            foreach ($promotionLineItems->filter(static fn (LineItem $item) => (bool) $item->getReferencedId()) as $lineItem) {
                $discountLineItems->add($lineItem);
            }
        }
        if ($behavior->hasPermission(self::PIN_AUTOMATIC_PROMOTIONS)) {
            foreach ($promotionLineItems->filter(static fn (LineItem $item) => !$item->getReferencedId()) as $lineItem) {
                $discountLineItems->add($lineItem);
            }
        }

        return $discountLineItems;
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
        if ($data->has(self::CACHE_KEY_AUTO)) {
            return $data->get(self::CACHE_KEY_AUTO);
        }

        $criteria = new Criteria();
        $criteria
            ->addFilter(new PermittedAutomaticPromotions($context->getSalesChannelId()))
            ->addAssociations(self::REQUIRED_DAL_ASSOCIATIONS);

        $automaticPromotions = $this->gateway->get($criteria, $context);

        $data->set(self::CACHE_KEY_AUTO, $automaticPromotions->getElements());

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
        // create a new cached list that is empty at first
        if (!$data->has(self::CACHE_KEY_CODE)) {
            $data->set(self::CACHE_KEY_CODE, new CartPromotionsDataDefinition());
        }

        // load it
        /** @var CartPromotionsDataDefinition $promotionsList */
        $promotionsList = $data->get(self::CACHE_KEY_CODE);

        // our data is a runtime cached structure.
        // but when line items get removed, the collect function gets called multiple times.
        // in the first iterations we still have a promotion code item
        // and then it is suddenly gone. so we also have to remove
        // entities from our cache if the code is suddenly not provided anymore.
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
        $salesChannelId = $context->getSalesChannelId();

        foreach ($codesToFetch as $currentCode) {
            // try to find a global code first because
            // that search has fewer data involved
            $globalCriteria = new Criteria();
            $globalCriteria
                ->addFilter(new PermittedGlobalCodePromotions([$currentCode], $salesChannelId))
                ->addAssociations(self::REQUIRED_DAL_ASSOCIATIONS);

            $foundPromotions = $this->gateway->get($globalCriteria, $context);
            if ($foundPromotions->count() === 0) {
                // no global code, so try with an individual code instead
                $individualCriteria = new Criteria();
                $individualCriteria
                    ->addFilter(new PermittedIndividualCodePromotions([$currentCode], $salesChannelId))
                    ->addAssociations(self::REQUIRED_DAL_ASSOCIATIONS);

                $foundPromotions = $this->gateway->get($individualCriteria, $context);
            }

            // if we finally have found promotions, add them to our list for the current code
            $promotionsList->addCodePromotions($currentCode, $foundPromotions->getElements());
        }

        // update our cached list with the latest cleaned array
        $data->set(self::CACHE_KEY_CODE, $promotionsList);

        return $promotionsList;
    }

    /**
     * Check if max allowed redemption of promotion have been reached or not.
     * If max redemption has been reached, promotion will not be added
     */
    private function isEligible(PromotionEntity $promotion, ?string $customerId, ?string $currentOrderId): bool
    {
        // code is already applied to this order, so it's should be valid
        if ($currentOrderId && $this->isUsedInCurrentOrder($promotion->getId(), $currentOrderId)) {
            return true;
        }

        // order count invalid
        if (!$promotion->isOrderCountValid()) {
            return false;
        }

        // order count for this customer invalid
        if ($customerId !== null && !$promotion->isOrderCountPerCustomerCountValid($customerId)) {
            return false;
        }

        // check if no discounts have been set
        if (!$promotion->hasDiscount()) {
            return false;
        }

        return true;
    }

    private function isUsedInCurrentOrder(string $promotionId, string $orderId): bool
    {
        $foundPromotionInThisOrder = $this->connection->fetchOne('SELECT 1 FROM order_line_item WHERE order_id = :orderId AND promotion_id = :promotionId', [
            'orderId' => Uuid::fromHexToBytes($orderId),
            'promotionId' => Uuid::fromHexToBytes($promotionId),
        ]);

        return $foundPromotionInThisOrder === '1';
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
     * @throws PromotionException
     *
     * @return array<LineItem>
     */
    private function buildDiscountLineItems(string $code, PromotionEntity $promotion, Cart $original, SalesChannelContext $context): array
    {
        if (!$promotion->getDiscounts()) {
            return [];
        }

        $factor = $context->getCurrency()->getIsSystemDefault() ? 1.0 : $context->getCurrency()->getFactor();

        $lineItems = [];

        foreach ($promotion->getDiscounts()->getElements() as $discount) {
            $discountItem = $this->itemBuilder->buildDiscountLineItem(
                $code,
                $promotion,
                $discount,
                $context->getCurrencyId(),
                $factor
            );

            $originalCodeItem = $original->getLineItems()->firstWhere(static function (LineItem $item) use ($code, $discount) {
                return ($item->getReferencedId() ?? '') === $code && $item->getPayloadValue('discountId') === $discount->getId();
            });

            if ($originalCodeItem && \count($originalCodeItem->getExtensions()) > 0) {
                $discountItem->setExtensions($originalCodeItem->getExtensions());
            }

            $lineItems[] = $discountItem;
        }

        return $lineItems;
    }

    private function hasLineItemToDiscount(Cart $cart): bool
    {
        return $cart->getLineItems()->firstWhere(
            static fn (LineItem $lineItem) => $lineItem->getType() !== PromotionProcessor::LINE_ITEM_TYPE,
        ) !== null;
    }
}
