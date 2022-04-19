<?php declare(strict_types=1);

namespace Shopware\Core\System;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\DependencyInjection\CompilerPass\RedisNumberRangeIncrementerCompilerPass;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class System extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('sales_channel.xml');
        $loader->load('country.xml');
        $loader->load('currency.xml');
        $loader->load('custom_entity.xml');
        $loader->load('locale.xml');
        $loader->load('snippet.xml');
        $loader->load('salutation.xml');
        $loader->load('tax.xml');
        $loader->load('unit.xml');
        $loader->load('user.xml');
        $loader->load('integration.xml');
        $loader->load('state_machine.xml');
        $loader->load('configuration.xml');
        $loader->load('number_range.xml');
        $loader->load('tag.xml');

        $container->addCompilerPass(new SalesChannelEntityCompilerPass());
        $container->addCompilerPass(new RedisNumberRangeIncrementerCompilerPass());
    }

    public function boot(): void
    {
        parent::boot();

        $this->container->get(CustomEntityRegistrar::class)->register();
    }
}
