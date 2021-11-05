<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Hook;

class Hook
{
    private array $services;

    public function __construct(array $services = [])
    {
        $this->services = [];
        foreach ($services as $service) {
            if (!$service instanceof HookAwareInterface) {
                throw new \RuntimeException(sprintf(
                    'Only services implementing the %s can be injected into hooks, but service %s does not implement that interface.',
                    HookAwareInterface::class,
                    \get_class($service)
                ));
            }

            $this->services[$service->getServiceName()] = $service;
        }
    }

    public function getServices(): array
    {
        return $this->services;
    }
}
