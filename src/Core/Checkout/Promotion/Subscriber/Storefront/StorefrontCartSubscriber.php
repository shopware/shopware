<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber\Storefront;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
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
        private readonly EventDispatcherInterface $eventDispatcher,
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
            // remove other discounts of the promotion that should be deleted
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

        if ($lineItem->getPayloadValue('discountType') !== PromotionDiscountEntity::TYPE_FIXED_UNIT) {
            return;
        }

        if (!$lineItem->hasPayloadValue('discountId')) {
            return;
        }

        $discountId = $lineItem->getPayloadValue('discountId');

        $removeThisDiscounts = $lineItems->filter(static fn (LineItem $lineItem) => $lineItem->getPayloadValue('discountId') === $discountId);

        foreach ($removeThisDiscounts as $discountItem) {
            $cart->remove($discountItem->getId());
        }
    }

    private function removeOtherDiscountsOfPromotion(Cart $cart, LineItem $removedLineItem, SalesChannelContext $context): void
    {
        $lineItemsOfSamePromotion = $cart->getLineItems()
            ->filter(fn (LineItem $lineItem) => $lineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE && $lineItem->getPayloadValue('promotionId') === $removedLineItem->getPayloadValue('promotionId'));

        foreach ($lineItemsOfSamePromotion as $lineItemOfSamePromotion) {
            $cart->remove($lineItemOfSamePromotion->getId());

            $this->eventDispatcher->dispatch(new BeforeLineItemRemovedEvent($lineItemOfSamePromotion, $cart, $context));
        }
    }

    private function addCode(string $code, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->addCode($code);
    }

    private function removeCode(string $code, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->removeCode($code);
    }

    private function blockPromotion(string $id, Cart $cart): void
    {
        $extension = $this->getExtension($cart);
        $extension->blockPromotion($id);
    }

    private function getExtension(Cart $cart): CartExtension
    {
        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);
        if ($extension === null) {
            // If the extension is not present, we create a new one
            // to ensure that we can add codes and promotions to it.
            $extension = new CartExtension();
            $cart->addExtension(CartExtension::KEY, $extension);
        }

        return $extension;
    }
}
