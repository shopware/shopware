<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class DiscountFacade
{
    private LineItem $item;

    /**
     * @internal
     */
    public function __construct(LineItem $item)
    {
        $this->item = $item;
    }

    public function getId(): string
    {
        return $this->item->getId();
    }

    public function getLabel(): ?string
    {
        return $this->item->getLabel();
    }
}
