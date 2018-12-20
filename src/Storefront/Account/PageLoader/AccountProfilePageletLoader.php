<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Account\Page\CustomerPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountProfilePageletLoader implements PageLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct()
    {
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @return CustomerPageletStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): CustomerPageletStruct
    {
        return new CustomerPageletStruct($context->getCustomer());
    }
}
