<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class FeatureFlagCompilerPass implements CompilerPassInterface
{
    private const INACTIVE_ALIAS_PARAMETER_PREFIX = 'shopware.inactiveFeature.alias.';

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

        foreach ($container->getParameterBag()->all() as $name => $flag) {
            if (!str_starts_with($name, self::INACTIVE_ALIAS_PARAMETER_PREFIX)) {
                continue;
            }

            $aliasId = substr($name, \strlen(self::INACTIVE_ALIAS_PARAMETER_PREFIX));
            if (!\is_string($flag) || !$container->hasAlias($aliasId)) {
                throw new \RuntimeException('Invalid inactive feature alias marker "' . $name . '"');
            }

            if (Feature::has($flag) && Feature::isActive($flag)) {
                $container->removeAlias($aliasId);
            }

            $container->getParameterBag()->remove($name);
        }
    }
}
