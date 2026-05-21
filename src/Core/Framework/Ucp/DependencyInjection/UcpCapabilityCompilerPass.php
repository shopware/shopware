<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;
use Shopware\Core\Framework\Ucp\Capability\UcpCapability;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Collects every service tagged `ucp.capability` into the central CapabilityRegistry.
 * Tagged services MUST implement {@see UcpCapability}.
 *
 * @internal
 */
#[Package('framework')]
class UcpCapabilityCompilerPass implements CompilerPassInterface
{
    public const TAG = 'ucp.capability';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CapabilityRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(CapabilityRegistry::class);
        $taggedServices = $container->findTaggedServiceIds(self::TAG);

        $seenNames = [];
        $references = [];

        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if ($class === null) {
                throw UcpException::capabilityTagInvalid($id, 'service has no class');
            }

            foreach ($tags as $tag) {
                // The tag's `name` attribute is consumed by Symfony to identify the tag itself,
                // so we use `capability` for the actual UCP capability identifier.
                $name = $tag['capability'] ?? null;
                if (!\is_string($name) || $name === '') {
                    throw UcpException::capabilityTagInvalid(
                        $id,
                        'missing required "capability" attribute (UCP capability identifier, e.g. "dev.ucp.shopping.cart")'
                    );
                }

                if (isset($seenNames[$name])) {
                    throw UcpException::capabilityTagInvalid(
                        $id,
                        \sprintf('duplicate capability "%s" — already registered by "%s"', $name, $seenNames[$name])
                    );
                }

                $seenNames[$name] = $id;
                $references[$name] = new Reference($id);
            }
        }

        $registry->setArgument('$capabilities', $references);
    }
}
