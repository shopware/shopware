<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber\Storefront;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class StorefrontCartSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY_PROMOTION_CODES = 'cart-promotion-codes';

    /**
     * @var Session
     */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LineItemAddedEvent::class => 'onLineItemAdded',
            LineItemRemovedEvent::class => 'onLineItemRemoved',
            CheckoutOrderPlacedEvent::class => 'resetCodes',
        ];
    }

    public function resetCodes(): void
    {
        $this->session->set(self::SESSION_KEY_PROMOTION_CODES, []);
    }

    /**
     * This function is called whenever a new line item has been
     * added to the cart from within the controllers.
     * We verify if we have a placeholder line item for a promotion
     * and add that code to our extension list.
     */
    public function onLineItemAdded(LineItemAddedEvent $event): void
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
    public function onLineItemRemoved(LineItemRemovedEvent $event): void
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
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws PayloadKeyNotFoundException
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

        $removeThisDiscounts = $lineItems->filter(static function (LineItem $lineItem) use ($discountId) {
            return $lineItem->hasPayloadValue('discountId') && $lineItem->getPayloadValue('discountId') === $discountId;
        });

        foreach ($removeThisDiscounts as $discountItem) {
            $cart->remove($discountItem->getId());
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
