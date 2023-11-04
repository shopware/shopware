<?php declare(strict_types=1);

namespace Shopware\Core\System;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\DependencyInjection\CompilerPass\RedisNumberRangeIncrementerCompilerPass;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('core')]
class System extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

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
        $loader->load('metrics.xml');
        $loader->load('snippet.xml');
        $loader->load('salutation.xml');
        $loader->load('tax.xml');
        $loader->load('tax_provider.xml');
        $loader->load('unit.xml');
        $loader->load('user.xml');
        $loader->load('integration.xml');
        $loader->load('state_machine.xml');
        $loader->load('configuration.xml');
        $loader->load('number_range.xml');
        $loader->load('tag.xml');

        $container->addCompilerPass(new SalesChannelEntityCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new RedisNumberRangeIncrementerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }

    public function boot(): void
    {
        parent::boot();

        $this->container->get(CustomEntityRegistrar::class)->register();
    }
}
