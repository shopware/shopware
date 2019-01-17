<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
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
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @return AccountRegistrationPageletStruct
     */
    public function load(InternalRequest $request, CheckoutContext $context): AccountRegistrationPageletStruct
    {
        return new AccountRegistrationPageletStruct();
    }
}
