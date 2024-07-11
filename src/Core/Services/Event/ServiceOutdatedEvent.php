<?php declare(strict_types=1);

namespace Shopware\Core\Services\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
readonly class ServiceOutdatedEvent implements ShopwareEvent
{
    public function __construct(public string $serviceName, private Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
