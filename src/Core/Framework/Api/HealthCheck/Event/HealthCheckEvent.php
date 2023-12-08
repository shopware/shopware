<?php

declare(strict_types=1);


namespace Shopware\Core\Framework\Api\HealthCheck\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class HealthCheckEvent extends Event
{
    public function __construct(
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
