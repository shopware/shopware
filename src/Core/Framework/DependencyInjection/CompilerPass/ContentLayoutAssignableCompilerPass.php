<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Schema\ContentLayoutAssignableEntityResolver;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutAssignableCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentLayoutAssignableEntityResolver::class)) {
            return;
        }

        $sources = $container->findTaggedServiceIds('content_system.context_factory');

        $entityTypes = [];
        foreach ($sources as $serviceId => $tags) {
            $sourceDefinition = $container->getDefinition($serviceId);

            if ($sourceDefinition->getClass() === null) {
                continue;
            }

            $entityTypes[] = $this->extractEntityType($container, $serviceId, $sourceDefinition->getArguments());
        }

        $resolver = $container->getDefinition(ContentLayoutAssignableEntityResolver::class);
        $resolver->setArgument(0, $entityTypes);
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function extractEntityType(ContainerBuilder $container, string $serviceId, array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            $referencedId = (string) $argument;

            if (!$container->hasDefinition($referencedId)) {
                continue;
            }

            $referencedClass = $container->getDefinition($referencedId)->getClass();

            if ($referencedClass === null || !class_exists($referencedClass)) {
                continue;
            }

            if (!is_subclass_of($referencedClass, AbstractContentLayoutAssignableDefinition::class)) {
                continue;
            }

            /** @var AbstractContentLayoutAssignableDefinition $instance */
            $instance = new $referencedClass();

            return $instance->getContentLayoutEntityType();
        }

        throw DependencyInjectionException::missingAssignableDefinition($serviceId, 'content_system.context_factory');
    }
}
