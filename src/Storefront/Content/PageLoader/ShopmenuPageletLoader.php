<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShopmenuPageletLoader implements PageLoader
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

    public function load(PageRequest $request, CheckoutContext $context): ShopmenuPageletStruct
    {
        $page = new ShopmenuPageletStruct();
        $salesChannel = $context->getSalesChannel();

        $page->setApplication($salesChannel);

        return $page;
    }
}
