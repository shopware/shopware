<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartStorageCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

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

        $phpLoader = new PhpFileLoader($container, $locator);
        $phpLoader->load('cart.php');
        $phpLoader->load('customer.php');
        $phpLoader->load('document.php');
        $phpLoader->load('order.php');
        $phpLoader->load('payment.php');
        $phpLoader->load('rule.php');
        $phpLoader->load('promotion.php');
        $phpLoader->load('shipping.php');

        $phpLoader->load('documentV2.php');
    }
}
