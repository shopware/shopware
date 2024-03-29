<?php

namespace Scripts\Examples;

use Doctrine\DBAL\Connection;
use Shopware\Core\HttpKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class BaseScript
{
    public ContainerInterface $container;

    public function __construct(public readonly KernelInterface $kernel)
    {
        $this->container = $kernel->getContainer();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
