<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;

/**
 * The DiscountFacade is a wrapper around a newly created discount.
 * Note that this wrapper is independent from the line-item that was added for this discount.
 *
 * @script-service cart_manipulation
 */
#[Package('checkout')]
class DiscountFacade
{
    /**
     * @internal
     */
    public function __construct(private LineItem $item)
    {
    }

    /**
     * `getId()` returns the id of the line-item that was added with this discount.
     *
     * @return string The id of the discount line-item.
     */
    public function getId(): string
    {
        return $this->item->getId();
    }

    /**
     * `getLabel()` returns the translated label of the line-item that was added with this discount.
     *
     * @return string|null The translated label of the discount line-item.
     */
    public function getLabel(): ?string
    {
        return $this->item->getLabel();
    }
}
