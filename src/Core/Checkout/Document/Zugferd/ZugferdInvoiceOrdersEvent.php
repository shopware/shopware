<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
final class ZugferdInvoiceOrdersEvent extends DocumentOrderEvent
{
}
