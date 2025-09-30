<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * This event can be used to react to the creation of a new context.
 * It must be used very carefully, as it practically effects every part of Shopware.
 */
#[Package('framework')]
final class ContextCreatedEvent
{
    public function __construct(
        public Context $context,
    ) {
    }
}
