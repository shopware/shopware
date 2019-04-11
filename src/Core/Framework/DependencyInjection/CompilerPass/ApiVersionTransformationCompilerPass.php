<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Api\VersionTransformation\ApiVersionTransformation;
use Shopware\Core\Framework\Api\VersionTransformation\VersionTransformationRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiVersionTransformationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $transformationClasses = array_keys($container->findTaggedServiceIds('shopware.api.version_transformation'));
        foreach ($transformationClasses as $transformationClass) {
            if (!is_subclass_of($transformationClass, ApiVersionTransformation::class)) {
                throw new \Exception(sprintf(
                    'Api version transformation "%s" does not implement required interface "%s".',
                    $transformationClass,
                    ApiVersionTransformation::class
                ));
            }
        }

        $collectionDefinition = $container->getDefinition(VersionTransformationRegistry::class);
        $collectionDefinition->addMethodCall('buildTransformationIndex', [$transformationClasses]);
    }
}
