<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class ZugferdInvoiceItemAddedEvent extends Event
{
    public function __construct(
        public readonly ZugferdDocument $document,
        public readonly OrderLineItemEntity $lineItem,
        public readonly string $parentPosition
    ) {
    }
}
