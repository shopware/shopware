<?php

namespace Script;

use Doctrine\DBAL\Connection;
use Shopware\Core\HttpKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseScript
{
    public ContainerInterface $container;
    public HttpKernel $kernel;

    public function __construct(HttpKernel $kernel)
    {
        $this->kernel = $kernel;
        $this->container = $kernel->getKernel()->getContainer();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
