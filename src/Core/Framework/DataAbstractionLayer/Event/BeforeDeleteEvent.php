<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Use `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent` instead
 */
#[Package('core')]
class BeforeDeleteEvent extends EntityDeleteEvent
{
}
