<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
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

    /**
     * @param AccountProfilePageletRequest $request
     * @param CheckoutContext              $context
     *
     * @return AccountProfilePageletStruct
     */
    public function load(AccountProfilePageletRequest $request, CheckoutContext $context): AccountProfilePageletStruct
    {
        $page = new AccountProfilePageletStruct();
        $page->setCustomer($context->getCustomer());

        return $page;
    }
}
