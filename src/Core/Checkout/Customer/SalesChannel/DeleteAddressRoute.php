<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class DeleteAddressRoute extends AbstractDeleteAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $addressRepository)
    {
    }

    public function getDecorated(): AbstractDeleteAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/address/{addressId}',
        name: 'store-api.account.address.delete',
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true],
        methods: ['DELETE']
    )]
    public function delete(string $addressId, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $customer);

        if (
            $addressId === $customer->getDefaultBillingAddressId()
            || $addressId === $customer->getDefaultShippingAddressId()
        ) {
            throw CustomerException::cannotDeleteDefaultAddress($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        if ($addressId === $customer->getActiveBillingAddress()?->getId()) {
            /** @deprecated tag:v6.8.0 - Use setter instead */
            $customer->assign(['activeBillingAddress' => $customer->getDefaultBillingAddress()]);
            // $customer->setActiveBillingAddress($customer->getDefaultBillingAddress());
        }

        if ($addressId === $customer->getActiveShippingAddress()?->getId()) {
            /** @deprecated tag:v6.8.0 - Use setter instead */
            $customer->assign(['activeShippingAddress' => $customer->getDefaultShippingAddress()]);
            // $customer->setActiveShippingAddress($customer->getDefaultShippingAddress());
        }

        return new NoContentResponse();
    }
}
