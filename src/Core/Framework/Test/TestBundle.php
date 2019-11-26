<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TestBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveDeprecatedServicesPass());

        parent::build($container);
    }
}
