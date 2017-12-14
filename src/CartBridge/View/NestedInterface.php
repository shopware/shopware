<?php declare(strict_types=1);

namespace Shopware\CartBridge\View;

interface NestedInterface
{
    public function getChildren(): ViewLineItemCollection;
}
