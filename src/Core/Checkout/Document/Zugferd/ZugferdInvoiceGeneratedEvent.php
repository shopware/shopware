<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class ZugferdInvoiceGeneratedEvent extends Event
{
    public function __construct(
        public readonly ZugferdDocument $document,
        public readonly OrderEntity $order,
        public readonly DocumentConfiguration $config,
        public readonly Context $context
    ) {
    }
}
