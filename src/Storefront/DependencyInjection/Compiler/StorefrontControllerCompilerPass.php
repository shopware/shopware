<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection\Compiler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Ensures backward compatibility for controllers not using autoconfigure.
 * Plugins may still use manual configuration.
 */
#[Package('framework')]
class StorefrontControllerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Process tagged controllers to avoid loading all classes
        $taggedControllers = $container->findTaggedServiceIds('controller.service_arguments');

        foreach (array_keys($taggedControllers) as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            // Use is_subclass_of with autoload=false to prevent triggering file-level deprecations
            // that Symfony places before class declarations (avoids loading unnecessary files)
            if (!is_subclass_of($class, StorefrontController::class, false)) {
                continue;
            }

            // Legacy controllers need manual configuration
            if (!$definition->isAutoconfigured()) {
                if (!$definition->hasTag('controller.service_arguments')) {
                    $definition->addTag('controller.service_arguments');
                }

                if (!$definition->hasTag('container.service_subscriber')) {
                    $definition->addTag('container.service_subscriber');
                }

                $hasSetContainer = false;
                foreach ($definition->getMethodCalls() as $methodCall) {
                    if ($methodCall[0] === 'setContainer') {
                        $hasSetContainer = true;
                        break;
                    }
                }

                // PSR interface provides service locator, not full container
                if (!$hasSetContainer && !$definition->isAutowired()) {
                    $definition->addMethodCall('setContainer', [
                        new Reference('Psr\Container\ContainerInterface'),
                    ]);
                }
            }

            // Controllers must be public for routing
            if (!$definition->isPublic()) {
                $definition->setPublic(true);
            }
        }
    }
}
