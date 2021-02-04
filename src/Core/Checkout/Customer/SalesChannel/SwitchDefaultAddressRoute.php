<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultBillingAddressEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultShippingAddressEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SwitchDefaultAddressRoute extends AbstractSwitchDefaultAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $addressRepository, EntityRepositoryInterface $customerRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractSwitchDefaultAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Patch(
     *      path="/account/address/default-shipping/{addressId}",
     *      summary="Sets the default shipping address",
     *      operationId="defaultShippingAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Parameter(
     *        name="addressId",
     *        in="path",
     *        description="Address ID",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @OA\Patch(
     *      path="/account/address/default-billing/{addressId}",
     *      summary="Sets the default billing address",
     *      operationId="defaultBillingAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Parameter(
     *        name="addressId",
     *        in="path",
     *        description="Address ID",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @LoginRequired()
     * @Route(path="/store-api/account/address/default-shipping/{addressId}", name="store-api.account.address.change.default.shipping", methods={"PATCH"}, defaults={"type" = "shipping"})
     * @Route(path="/store-api/account/address/default-billing/{addressId}", name="store-api.account.address.change.default.billing", methods={"PATCH"}, defaults={"type" = "billing"})
     */
    public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $customer);

        switch ($type) {
            case 'billing':
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
