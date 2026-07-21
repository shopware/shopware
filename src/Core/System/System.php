<?php declare(strict_types=1);

namespace Shopware\Core\System;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\DependencyInjection\CompilerPass\NumberRangeIncrementerCompilerPass;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('framework')]
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

        $configLocator = new FileLocator(__DIR__ . '/DependencyInjection/');

        $loader = new XmlFileLoader($container, $configLocator);
        $phpLoader = new PhpFileLoader($container, $configLocator);
        $phpLoader->load('sales_channel.php');
        $loader->load('country.xml');
        $loader->load('currency.xml');
        $phpLoader->load('custom_entity.php');
        $loader->load('locale.xml');
        $loader->load('snippet.xml');
        $phpLoader->load('salutation.php');
        $phpLoader->load('tax.php');
        $phpLoader->load('tax_provider.php');
        $loader->load('unit.xml');
        $loader->load('user.xml');
        $loader->load('integration.xml');
        $phpLoader->load('state_machine.php');
        $phpLoader->load('configuration.php');
        $phpLoader->load('number_range.php');
        $loader->load('tag.xml');

        $phpLoader->load('consent.php');
        $phpLoader->load('usage_data.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('services_test.php');
        }

        $container->addCompilerPass(new SalesChannelEntityCompilerPass());
        $container->addCompilerPass(new NumberRangeIncrementerCompilerPass());
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        $this->container->get(CustomEntityRegistrar::class)->register();
    }
}
