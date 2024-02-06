<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\HealthCheck\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class HealthCheckEvent extends Event
{
    private array $serviceDataList = [];

    public function __construct(
        public readonly Context $context
    ) {
    }

    /**
     * @param string $serviceName
     * @param array $data
     *
     * @return void
     */
    public function addServiceData(string $serviceName, array $data): void
    {
        $this->serviceDataList[$serviceName] = $data;
    }

    /**
     * @return array
     */
    public function getServiceDataList(): array
    {
        return $this->serviceDataList;
    }
}
