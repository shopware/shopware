<?php

namespace Shopware\Core\Framework\HealthCheck\Plugin;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Framework\HealthCheck\Event\HealthCheckEvent;

interface HealthCheckPluginInterface
{
    /**
     * @return string
     */
    public function getServiceName(): string;

    /**
     * @return bool
     */
    public function isExecutable(): bool;

    /**
     * @param HealthCheckEvent $event
     *
     * @return HealthCheckEvent
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function execute(HealthCheckEvent $event): HealthCheckEvent;
}
