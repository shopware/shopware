<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountRegistrationPageletLoader
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
     * @param AccountRegistrationPageletRequest $request
     * @param CheckoutContext                   $context
     *
     * @return AccountRegistrationPageletStruct
     */
    public function load(AccountRegistrationPageletRequest $request, CheckoutContext $context): AccountRegistrationPageletStruct
    {
        return new AccountRegistrationPageletStruct();
    }
}
