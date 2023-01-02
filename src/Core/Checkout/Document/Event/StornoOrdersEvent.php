<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Log\Package;
/**
 * @package customer-order
 */
#[Package('customer-order')]
final class StornoOrdersEvent extends DocumentOrderEvent
{
}
