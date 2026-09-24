<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class FeatureFlagCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $featureFlags = $container->getParameter('shopware.feature.flags');
        if (!\is_array($featureFlags)) {
            throw new \RuntimeException('Container parameter "shopware.feature.flags" needs to be an array');
        }

        Feature::registerFeatures($featureFlags);

        foreach (['shopware.feature' => false, 'shopware.inactiveFeature' => true] as $tagName => $removeWhenActive) {
            foreach ($container->findTaggedServiceIds($tagName) as $serviceId => $tags) {
                foreach ($tags as $tag) {
                    if (!isset($tag['flag'])) {
                        throw new \RuntimeException('"flag" is a required field for "' . $tagName . '" tags');
                    }

                    if ($tagName === 'shopware.inactiveFeature' && !Feature::has($tag['flag'])) {
                        continue;
                    }

                    if (Feature::isActive($tag['flag']) !== $removeWhenActive) {
                        continue;
                    }

                    $container->removeDefinition($serviceId);

                    break;
                }
            }
        }
    }
}
