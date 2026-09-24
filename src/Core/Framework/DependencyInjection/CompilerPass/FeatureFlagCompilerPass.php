<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
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

        foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
            if (!$container->hasAlias($previousClassName)) {
                continue;
            }

            foreach ((new \ReflectionClass($currentClassName))->getAttributes(ClassMoved::class) as $attribute) {
                $classMoved = $attribute->newInstance();
                if ($classMoved->previousClassName !== $previousClassName) {
                    continue;
                }

                $flag = $classMoved->version . '.0';
                if (Feature::has($flag) && Feature::isActive($flag)) {
                    $container->removeAlias($previousClassName);
                }
            }
        }

        // This deprecated interface is not a moved class and therefore has no ClassAliasRegistry entry.
        if (Feature::has('v6.8.0.0') && Feature::isActive('v6.8.0.0')) {
            $container->removeAlias('Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface');
        }
    }
}
