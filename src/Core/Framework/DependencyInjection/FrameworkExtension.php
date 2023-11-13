<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[Package('core')]
class FrameworkExtension extends Extension
{
    private const ALIAS = 'shopware';

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->addShopwareConfig($container, $this->getAlias(), $config);
    }

    private function addShopwareConfig(ContainerBuilder $container, string $alias, array $options): void
    {
        foreach ($options as $key => $option) {
            $key = $alias . '.' . $key;
            $container->setParameter($key, $option);

            /*
             * The route cache in dev mode checks on each request if its fresh. If you use the following expression
             * `defaults={"auth_required"="%shopware.api.api_browser.auth_required%"}` it also checks if the parameter
             * matches the value in the container. The expression always results in a string, but the value in the
             * container is a boolean. So they never match. To workaround this, we add this as an additional string
             * parameter. So in the dynamic use case you have to use `defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}`
             */
            if ($key === 'shopware.api.api_browser.auth_required') {
                $container->setParameter('shopware.api.api_browser.auth_required_str', (string) (int) $option);
            }

            if (\is_array($option)) {
                $this->addShopwareConfig($container, $key, $option);
            }
        }
    }
}
