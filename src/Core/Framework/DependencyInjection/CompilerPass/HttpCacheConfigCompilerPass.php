<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class HttpCacheConfigCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $config = $this->getConfig($container, 'framework');

        $container->getDefinition('http_kernel.cache')
            ->replaceArgument(3, $config['http_cache'] ?? []);

        if (Feature::isActive('LONGRUNNING_ENVIRONMENT')) {
            // same as `framework.http_cache.enabled: false`, regardless of the project config, see Kernel::handle()
            $container->getDefinition('http_cache')->setPublic(false);
        }
    }
}
