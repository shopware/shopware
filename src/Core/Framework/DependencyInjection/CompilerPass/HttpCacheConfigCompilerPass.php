<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class HttpCacheConfigCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $config = $this->getConfig($container, 'framework');

        $container->getDefinition('http_kernel.cache')
            ->replaceArgument(3, $config['http_cache'] ?? []);
    }
}
