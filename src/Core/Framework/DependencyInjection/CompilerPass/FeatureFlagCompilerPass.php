<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FeatureFlagCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.feature');

        foreach ($services as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw new \RuntimeException('"flag" is a required field for "shopware.feature" tags');
                }

                if (Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }
    }
}
