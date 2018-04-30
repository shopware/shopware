<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Framework\DependencyInjection;

use Shopware\Framework\Event\NestedEventDispatcher;
use Shopware\Framework\Event\TraceableNestedEventDispatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class FrameworkExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'shopware';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->addShopwareConfig($container, 'shopware', $config);

        $this->registerNestedEventDispatcher($container);
    }

    private function addShopwareConfig(ContainerBuilder $container, string $alias, array $options): void
    {
        foreach ($options as $key => $option) {
            $container->setParameter($alias . '.' . $key, $option);

            if (is_array($option)) {
                $this->addShopwareConfig($container, $alias . '.' . $key, $option);
            }
        }
    }

    private function registerNestedEventDispatcher(ContainerBuilder $container)
    {
        if ($container->getParameter('kernel.debug')) {
            $container->register('shopware.framework.event.traceable_nested_event_dispatcher', TraceableNestedEventDispatcher::class)
                ->setDecoratedService('event_dispatcher')
                ->addArgument(new Reference('shopware.framework.event.traceable_nested_event_dispatcher.inner'))
                ->setPublic(false);

            return;
        }

        $container->register('shopware.framework.event.nested_event_dispatcher', NestedEventDispatcher::class)
            ->setDecoratedService('event_dispatcher')
            ->addArgument(new Reference('shopware.framework.event.nested_event_dispatcher.inner'))
            ->setPublic(false);
    }
}
