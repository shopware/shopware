<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Shopmenu;

use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShopmenuPageletLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function load(ShopmenuPageletRequest $request, CheckoutContext $context): ShopmenuPageletStruct
    {
        $page = new ShopmenuPageletStruct();
        $salesChannel = $context->getSalesChannel();

        $page->setApplication($salesChannel);

        return $page;
    }
}
