<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 - Will be removed.
 */
#[Package('after-sales')]
final class DeliveryNoteOrdersEvent extends DocumentOrderEvent
{
}
