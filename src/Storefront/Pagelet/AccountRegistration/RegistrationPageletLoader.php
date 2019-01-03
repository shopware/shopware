<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistrationPageletLoader
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
     * @param RegistrationPageletRequest $request
     * @param CheckoutContext            $context
     *
     * @return RegistrationPageletStruct
     */
    public function load(RegistrationPageletRequest $request, CheckoutContext $context): RegistrationPageletStruct
    {
        return new RegistrationPageletStruct();
    }
}
