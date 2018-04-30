<?php declare(strict_types=1);

namespace Shopware\Cart\LineItem;

interface NestedInterface
{
    public function getChildren(): CalculatedLineItemCollection;

    public function considerChildrenPrices(): bool;
}
