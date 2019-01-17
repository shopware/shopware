<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountProfilePageletLoader
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

    public function load(InternalRequest $request, CheckoutContext $context): AccountProfilePageletStruct
    {
        $page = new AccountProfilePageletStruct();
        $page->setCustomer($context->getCustomer());

        return $page;
    }
}
