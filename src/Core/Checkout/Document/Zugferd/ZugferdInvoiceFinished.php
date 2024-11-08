<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class ZugferdInvoiceFinished extends Event
{
    public function __construct(
        public readonly ZugferdDocument $document,
        public readonly OrderEntity $order,
        public readonly Context $context
    ) {
    }
}
