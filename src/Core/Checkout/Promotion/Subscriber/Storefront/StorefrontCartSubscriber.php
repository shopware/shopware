<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber\Storefront;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class StorefrontCartSubscriber implements EventSubscriberInterface
{
    final public const SESSION_KEY_PROMOTION_CODES = 'cart-promotion-codes';

    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
            BeforeLineItemRemovedEvent::class => 'onLineItemRemoved',
            CheckoutOrderPlacedEvent::class => 'resetCodes',
        ];
    }

    public function resetCodes(): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        if (!$mainRequest->hasSession()) {
            return;
        }

        $mainRequest->getSession()->set(self::SESSION_KEY_PROMOTION_CODES, []);
    }

    /**
     * This function is called whenever a new line item has been
     * added to the cart from within the controllers.
     * We verify if we have a placeholder line item for a promotion
     * and add that code to our extension list.
     */
    public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
    {
        if ($event->getLineItem()->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
            $code = $event->getLineItem()->getReferencedId();

            if ($code !== null && $code !== '') {
                $this->addCode($code, $event->getCart());
            }
        }
    }

    /**
     * This function is called whenever a line item is being removed
     * from the cart from within a controller.
     * We verify if it is a promotion item, and also remove that
     * code from our extension, if existing.
     */
    public function onLineItemRemoved(BeforeLineItemRemovedEvent $event): void
    {
        $cart = $event->getCart();

        if ($event->getLineItem()->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
            return;
        }

        $lineItem = $event->getLineItem();

        $code = $lineItem->getReferencedId();

        if (!empty($code)) {
            // promotion with code
            $this->checkFixedDiscountItems($cart, $lineItem);
            //remove other discounts of the promotion that should be deleted
            $this->removeOtherDiscountsOfPromotion($cart, $lineItem, $event->getSalesChannelContext());
            $this->removeCode($code, $cart);

            return;
        }

        // the user wants to remove an automatic added
        // promotions, so lets do this
        if ($lineItem->hasPayloadValue('promotionId')) {
            $promotionId = (string) $lineItem->getPayloadValue('promotionId');
            $this->blockPromotion($promotionId, $cart);
        }
    }

    /**
     * @throws CartException
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

        $discountId = $lineItem->getPayloadValue('discountId');

        $removeThisDiscounts = $lineItems->filter(static fn (LineItem $lineItem) => $lineItem->hasPayloadValue('discountId') && $lineItem->getPayloadValue('discountId') === $discountId);

        foreach ($removeThisDiscounts as $discountItem) {
            $cart->remove($discountItem->getId());
        }
    }

    private function removeOtherDiscountsOfPromotion(Cart $cart, LineItem $lineItem, SalesChannelContext $context): void
    {
        // ge all promotions from cart
        $lineItems = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        if ($lineItems->count() < 1) {
            return;
        }

        //filter them by the promotion which discounts should be deleted
        $lineItems = $lineItems->filter(fn (LineItem $promotionLineItem) => $promotionLineItem->getPayloadValue('promotionId') === $lineItem->getPayloadValue('promotionId'));

        if ($lineItems->count() < 1) {
            return;
        }

        $promotionLineItem = $lineItems->first();

        if ($promotionLineItem instanceof LineItem) {
            // this is recursive because we are listening on LineItemRemovedEvent, it will stop if there
            // are no discounts in the cart, that belong to the promotion that should be deleted
            $this->cartService->remove($cart, $promotionLineItem->getId(), $context);
        }
    }

    private function addCode(string $code, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->addCode($code);

        $cart->addExtension(CartExtension::KEY, $extension);
    }

    private function removeCode(string $code, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->removeCode($code);

        $cart->addExtension(CartExtension::KEY, $extension);
    }

    private function blockPromotion(string $id, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->blockPromotion($id);

        $cart->addExtension(CartExtension::KEY, $extension);
    }

    private function getExtension(Cart $cart): CartExtension
    {
        if (!$cart->hasExtension(CartExtension::KEY)) {
            $cart->addExtension(CartExtension::KEY, new CartExtension());
        }

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        return $extension;
    }
}
