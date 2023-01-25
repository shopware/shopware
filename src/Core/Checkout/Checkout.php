<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('core')]
class Checkout extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CartRedisCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('cart.xml');
        $loader->load('customer.xml');
        $loader->load('document.xml');
        $loader->load('order.xml');
        $loader->load('payment.xml');
        $loader->load('rule.xml');
        $loader->load('promotion.xml');
        $loader->load('shipping.xml');

        // test classes are only loaded in autoload dev, so they are not available when other projects run platform in test mode
        if ($container->getParameter('kernel.environment') === 'test' && class_exists('Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider')) {
            $loader->load('services_test.xml');
        }
    }
}
