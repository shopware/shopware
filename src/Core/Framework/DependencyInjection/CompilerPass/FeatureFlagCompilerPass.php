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

        foreach ($container->getAliases() as $aliasId => $alias) {
            if (!$alias->isDeprecated()) {
                continue;
            }

            $flag = null;
            if (isset(ClassAliasRegistry::ALIASES[$aliasId])) {
                foreach ((new \ReflectionClass(ClassAliasRegistry::ALIASES[$aliasId]))->getAttributes(ClassMoved::class) as $attribute) {
                    $classMoved = $attribute->newInstance();
                    if ($classMoved->previousClassName === $aliasId) {
                        $flag = $classMoved->version . '.0';

                        break;
                    }
                }
            } elseif (\class_exists($aliasId) || \interface_exists($aliasId)) {
                $docComment = (new \ReflectionClass($aliasId))->getDocComment();
                if (\is_string($docComment) && \preg_match('/@deprecated\s+tag:(v\d+\.\d+\.\d+(?:\.\d+)?)/', $docComment, $matches)) {
                    $flag = substr_count($matches[1], '.') === 2 ? $matches[1] . '.0' : $matches[1];
                }
            }

            if ($flag !== null && Feature::has($flag) && Feature::isActive($flag)) {
                $container->removeAlias($aliasId);
            }
        }
    }
}
