<?php declare(strict_types=1);

namespace Shopware\Core\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceUpdatedEvent implements ShopwareEvent
{
    public function __construct(public string $service, private Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
