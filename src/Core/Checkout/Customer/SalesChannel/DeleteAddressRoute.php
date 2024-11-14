<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
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

        $activeBillingAddress = $customer->getActiveBillingAddress();
        $activeShippingAddress = $customer->getActiveShippingAddress();

        if (
            ($activeBillingAddress && $addressId === $activeBillingAddress->getId())
            || ($activeShippingAddress && $addressId === $activeShippingAddress->getId())
        ) {
            throw CustomerException::cannotDeleteActiveAddress($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        return new NoContentResponse();
    }
}
