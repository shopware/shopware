<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed in 6.9.0
 *
 * @property ContainerInterface $container
 *
 * This trait provides backward compatibility for controllers that directly access
 * services from the container without declaring them in getSubscribedServices().
 * All usages should be migrated to proper service subscription.
 */
#[Package('framework')]
trait DeprecatedContainerAccessTrait
{
    /**
     * @deprecated tag:v6.8.0 - Use service subscription instead
     */
    protected function getServiceDeprecated(string $id): ?object
    {
        trigger_deprecation(
            'shopware/storefront',
            '6.8.0',
            'Direct container access via $this->container->get("%s") in %s is deprecated. '
            . 'Declare the service in getSubscribedServices() method instead.',
            $id,
            static::class
        );

        if (!$this->container->has($id)) {
            return null;
        }

        return $this->container->get($id);
    }

    /**
     * @deprecated tag:v6.8.0 - Use service subscription instead
     */
    protected function hasServiceDeprecated(string $id): bool
    {
        trigger_deprecation(
            'shopware/storefront',
            '6.6.0',
            'Checking service availability via hasServiceDeprecated("%s") in %s is deprecated. '
            . 'Declare the service as optional in getSubscribedServices() method instead.',
            $id,
            static::class
        );

        return $this->container->has($id);
    }
}
