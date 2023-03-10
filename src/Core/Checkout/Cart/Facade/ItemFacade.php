<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Facade\ArrayFacade;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The ItemFacade is a wrapper around one line-item.
 *
 * @script-service cart_manipulation
 */
#[Package('checkout')]
class ItemFacade
{
    /**
     * @internal
     */
    public function __construct(
        private LineItem $item,
        private ScriptPriceStubs $priceStubs,
        private CartFacadeHelper $helper,
        private SalesChannelContext $context
    ) {
    }

    /**
     * `getPrice()` returns the calculated price of the line-item.
     *
     * @return PriceFacade|null Returns the price of the line-item as a `PriceFacade` or null if the line-item has no calculated price.
     */
    public function getPrice(): ?PriceFacade
    {
        if ($this->item->getPrice()) {
            return new PriceFacade($this->item, $this->item->getPrice(), $this->priceStubs, $this->context);
        }

        return null;
    }

    /**
     * `take()` splits an existing line-item by a given quantity.
     * It removes the given quantity from the existing line-item and returns a new line-item with exactly that quantity.
     *
     * @param int $quantity The quantity that should be taken.
     * @param string|null $key Optional: The id of the new line-item. A random UUID will be used if none is provided.
     *
     * @return ItemFacade|null Returns the new line-item as an `ItemFacade` or null if taking is not possible because the line-item has no sufficient quantity.
     *
     * @example split-product/split-product.twig Take a quantity of 2 from an existing product line-item and add it to the cart again.
     */
    public function take(int $quantity, ?string $key = null): ?ItemFacade
    {
        if (!$this->item->isStackable()) {
            return null;
        }

        if ($quantity >= $this->item->getQuantity()) {
            return null;
        }

        $new = clone $this->item;
        $new->setId($key ?? Uuid::randomHex());
        $new->setQuantity($quantity);

        $this->item->setQuantity(
            $this->item->getQuantity() - $quantity
        );

        return new ItemFacade($new, $this->priceStubs, $this->helper, $this->context);
    }

    /**
     * `getId()` returns the id of the line-item.
     *
     * @return string Returns the id.
     */
    public function getId(): string
    {
        return $this->item->getId();
    }

    /**
     * `getReferenceId()` returns the id of the referenced entity of the line-item.
     * E.g. for product line-items this will return the id of the referenced product.
     *
     * @return string|null Returns the id of the referenced entity, or null if no entity is referenced.
     */
    public function getReferencedId(): ?string
    {
        return $this->item->getReferencedId();
    }

    /**
     * `getQuantity()` returns the quantity of the line-item.
     *
     * @return int Returns the quantity.
     */
    public function getQuantity(): int
    {
        return $this->item->getQuantity();
    }

    /**
     * `getLabel()` returns the translated label of the line-item.
     *
     * @return string|null Returns the translated label, or null if none exists.
     */
    public function getLabel(): ?string
    {
        return $this->item->getLabel();
    }

    /**
     * `getPayload()` returns the payload of this line-item.
     *
     * @return ArrayFacade Returns the payload as `ArrayFacade`.
     */
    public function getPayload(): ArrayFacade
    {
        return new ArrayFacade(
            $this->item->getPayload(),
            function (array $payload): void {
                $this->item->setPayload($payload);
            }
        );
    }

    /**
     * `getChildren()` returns the child line-items of this line-item.
     *
     * @return ItemsFacade Returns the children as a `ItemsFacade`, that may be empty if no children exist.
     */
    public function getChildren(): ItemsFacade
    {
        return new ItemsFacade($this->item->getChildren(), $this->priceStubs, $this->helper, $this->context);
    }

    /**
     * `getType()` returns the type of this line-item.
     * Possible types include `product`, `discount`, `container`, etc.
     *
     * @return string The type of the line-item.
     */
    public function getType(): string
    {
        return $this->item->getType();
    }

    /**
     * @internal
     */
    public function getItem(): LineItem
    {
        return $this->item;
    }
}
