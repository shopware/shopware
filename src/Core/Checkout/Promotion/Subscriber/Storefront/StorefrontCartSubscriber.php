<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber\Storefront;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class StorefrontCartSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY_PROMOTION_CODES = 'cart-promotion-codes';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(Session $session, CartService $cartService)
    {
        $this->session = $session;
        $this->cartService = $cartService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LineItemAddedEvent::class => 'onLineItemAdded',
            LineItemQuantityChangedEvent::class => 'onLineItemQuantityChanged',
            LineItemRemovedEvent::class => 'onLineItemRemoved',
        ];
    }

    /**
     * This function is called whenever a new line item has been
     * added to the cart from within the controllers.
     * We verify if we have a placeholder line item for a promotion
     * and add that code to our global session list.
     * We also re-add all codes that the user
     * has previously added in case they might work now.
     */
    public function onLineItemAdded(LineItemAddedEvent $event): void
    {
        $this->setupSession();

        if ($event->getLineItem()->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
            /** @var string|null $code */
            $code = $event->getLineItem()->getReferencedId();

            if (!empty($code)) {
                $this->addToSession($code);
            }
        }

        $this->reAddPromotionsFromSession($event->getCart(), $event->getContext());
    }

    /**
     * This function is called whenever a line item quantity changes.
     * In this case we just make sure that we re-add all codes that the user
     * has previously added in case they might work now.
     */
    public function onLineItemQuantityChanged(LineItemQuantityChangedEvent $event): void
    {
        $this->setupSession();

        $this->reAddPromotionsFromSession($event->getCart(), $event->getContext());
    }

    /**
     * This function is called whenever a line item is being removed
     * from the cart from within a controller.
     * We verify if it is a promotion item, and also remove that
     * code from our global session, if existing.
     * We also re-add all codes that the user
     * has previously added in case they might work now.
     */
    public function onLineItemRemoved(LineItemRemovedEvent $event): void
    {
        $this->setupSession();

        $cart = $event->getCart();

        if ($event->getLineItem()->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
            $lineItem = $event->getLineItem();

            /** @var string|null $code */
            $code = $lineItem->getReferencedId();

            if (!empty($code)) {
                $this->checkFixedDiscountItems($cart, $lineItem);
                $this->removeFromSession($code);
            }
        }

        $this->reAddPromotionsFromSession($cart, $event->getContext());
    }

    /**
     * This function adds placeholder line items for promotions.
     * It will always add items for all codes that are existing in
     * the current session of the user.
     * Thus it will re-add promotions that have been added before
     * and where not explicitly removed by the user.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function reAddPromotionsFromSession(Cart $cart, SalesChannelContext $context): void
    {
        /** @var array $allSessionCodes */
        $allSessionCodes = $this->session->get(self::SESSION_KEY_PROMOTION_CODES);

        if (count($allSessionCodes) <= 0) {
            return;
        }

        /** @var array $codesInCart */
        $codesInCart = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->getReferenceIds();

        $builder = new PromotionItemBuilder();

        /** @var string $sessionCode */
        foreach ($allSessionCodes as $sessionCode) {
            // only add a new placeholder item if that
            // code is not already existing either as placeholder or real promotion item
            if (!in_array($sessionCode, $codesInCart, true)) {
                /** @var LineItem $lineItem */
                $lineItem = $builder->buildPlaceholderItem($sessionCode, $context->getContext()->getCurrencyPrecision());
                /* @var Cart $cart */
                $cart->add($lineItem);
            }
        }
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function checkFixedDiscountItems(Cart $cart, LineItem $lineItem): void
    {
        $lineItems = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        if ($lineItems->count() < 1) {
            return;
        }

        if (!$lineItem->hasPayloadValue('discountType')) {
            return;
        }

        if ($lineItem->getPayloadValue('discountType') !== PromotionDiscountEntity::TYPE_FIXED_UNIT) {
            return;
        }

        if (!$lineItem->hasPayloadValue('discountId')) {
            return;
        }

        /** @var string $discountId */
        $discountId = $lineItem->getPayloadValue('discountId');

        $removeThisDiscounts = $lineItems->filter(function ($lineItem) use ($discountId) {
            if ($lineItem->hasPayloadValue('discountId') && $lineItem->getPayloadValue('discountId') === $discountId) {
                return true;
            }

            return false;
        });

        foreach ($removeThisDiscounts as $discountItem) {
            $cart->remove($discountItem->getId());
        }
    }

    /**
     * if a customer adds a promotion code it is stored in the session
     * the promotion will be added each time if a change in cart occures
     * This ensures that is added and removed automatically if restrictions
     * of promotions fit or do not fit
     */
    private function addToSession(string $code): void
    {
        /** @var array $allCodes */
        $allCodes = $this->session->get(self::SESSION_KEY_PROMOTION_CODES);

        // add our new item
        if (!in_array($code, $allCodes, true)) {
            $allCodes[] = $code;
        }

        $this->session->set(self::SESSION_KEY_PROMOTION_CODES, $allCodes);
    }

    /**
     * if a customer removes a promotion code from the cart, he explicitly tells us
     * that he doesn't want it => remove it from session store to ensure it is not
     * added automatically any more
     */
    private function removeFromSession(string $code): void
    {
        /** @var array $allCodes */
        $allCodes = $this->session->get(self::SESSION_KEY_PROMOTION_CODES);

        // remove our code string from the list
        $allCodes = array_diff($allCodes, [$code]);

        $this->session->set(self::SESSION_KEY_PROMOTION_CODES, $allCodes);
    }

    /**
     * Creates an empty session list if not
     * already existing.
     */
    private function setupSession(): void
    {
        if (!$this->session->has(self::SESSION_KEY_PROMOTION_CODES)) {
            $this->session->set(self::SESSION_KEY_PROMOTION_CODES, []);
        }
    }
}
