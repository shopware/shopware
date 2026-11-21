<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Cookie\AppCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Shopware\Storefront\Framework\Cookie\LegacyCookieProviderBridge;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * Registers the {@see LegacyCookieProviderBridge} only when an extension still decorates or replaces the deprecated {@see CookieProviderInterface}.
 * Each of these services is reported once as a container build deprecation,
 * so the affected extension shows up in `debug:container --deprecations` and the profiler instead of in every request log.
 *
 * Remove together with {@see CookieProviderInterface} in v6.9.0.0.
 */
#[Package('discovery')]
class LegacyCookieProviderBridgePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(LegacyCookieProviderBridge::class)) {
            return;
        }

        $legacyProviders = $this->findLegacyProviders($container);
        if ($legacyProviders === []) {
            $container->removeDefinition(LegacyCookieProviderBridge::class);

            return;
        }

        foreach ($legacyProviders as $serviceId => $class) {
            trigger_deprecation(
                'shopware/storefront',
                '6.7.17.0',
                'The service "%s" (%s) decorates or replaces "%s", which will be removed in v6.9.0.0. Its cookie groups are still applied through a compatibility bridge. Add cookies with a listener for "%s" instead.',
                $serviceId,
                $class,
                CookieProviderInterface::class,
                CookieGroupCollectEvent::class,
            );
        }
    }

    /**
     * @return array<string, string> service id => class
     */
    private function findLegacyProviders(ContainerBuilder $container): array
    {
        $legacyProviders = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $decorated = $definition->getDecoratedService();
            if ($decorated === null || $decorated[0] !== CookieProviderInterface::class) {
                continue;
            }

            $class = $definition->getClass() ?? $id;
            if ($class === AppCookieProvider::class) {
                continue;
            }

            $legacyProviders[$id] = $class;
        }

        // Runs before the decoration is applied, so the id still points to the default provider unless an extension replaced it.
        // findDefinition() also resolves an alias an extension registered for the interface.
        if ($container->has(CookieProviderInterface::class)) {
            $class = $container->findDefinition(CookieProviderInterface::class)->getClass() ?? CookieProviderInterface::class;
            if ($class !== CookieProvider::class) {
                $legacyProviders[CookieProviderInterface::class] = $class;
            }
        }

        return $legacyProviders;
    }
}
