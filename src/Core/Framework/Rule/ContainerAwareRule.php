<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
interface ContainerAwareRule
{
    public function configureDependencies(ContainerInterface $container): void;
}
