<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
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

    public function load(InternalRequest $request, CheckoutContext $context): AccountLoginPageletStruct
    {
        return new AccountLoginPageletStruct();
    }
}
