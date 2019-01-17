<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountLoginPageletLoader
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
     * @param AccountLoginPageletRequest $request
     * @param CheckoutContext            $context
     *
     * @return AccountLoginPageletStruct
     */
    public function load(AccountLoginPageletRequest $request, CheckoutContext $context): AccountLoginPageletStruct
    {
        return new AccountLoginPageletStruct();
    }
}
