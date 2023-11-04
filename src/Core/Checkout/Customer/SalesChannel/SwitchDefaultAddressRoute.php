<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultBillingAddressEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultShippingAddressEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class SwitchDefaultAddressRoute extends AbstractSwitchDefaultAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @var EntityRepository
     */
    private $addressRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $addressRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->addressRepository = $addressRepository;
    }

    public function getDecorated(): AbstractSwitchDefaultAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/address/default-shipping/{addressId}', name: 'store-api.account.address.change.default.shipping', methods: ['PATCH'], defaults: ['type' => 'shipping', '_loginRequired' => true])]
    #[Route(path: '/store-api/account/address/default-billing/{addressId}', name: 'store-api.account.address.change.default.billing', methods: ['PATCH'], defaults: ['type' => 'billing', '_loginRequired' => true])]
    public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $customer);

        switch ($type) {
            case self::TYPE_BILLING:
                $data = [
                    'id' => $customer->getId(),
                    'defaultBillingAddressId' => $addressId,
                ];

                $event = new CustomerSetDefaultBillingAddressEvent($context, $customer, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
            default:
                $data = [
                    'id' => $customer->getId(),
                    'defaultShippingAddressId' => $addressId,
                ];

                $event = new CustomerSetDefaultShippingAddressEvent($context, $customer, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
        }

        $this->customerRepository->update([$data], $context->getContext());

        return new NoContentResponse();
    }
}
