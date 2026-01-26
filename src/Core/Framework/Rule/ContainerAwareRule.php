<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface ContainerAwareRule
{
    public function configureDependencies(ContainerInterface $container): void;
}
