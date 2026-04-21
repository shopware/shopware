<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartStorageCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('checkout')]
class Checkout extends Bundle
{
    private const DEPENDENCY_LOCATION = __DIR__ . '/DependencyInjection/';

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CartStorageCompilerPass());

        $locator = new FileLocator(self::DEPENDENCY_LOCATION);

        $loader = new XmlFileLoader($container, $locator);
        $loader->load('cart.xml');
        $loader->load('customer.xml');
        $loader->load('document.xml');
        $loader->load('order.xml');
        $loader->load('payment.xml');
        $loader->load('rule.xml');
        $loader->load('promotion.xml');
        $loader->load('shipping.xml');

        $phpLoader = new PhpFileLoader($container, $locator);
        $phpLoader->load('documentV2.php');
    }
}
