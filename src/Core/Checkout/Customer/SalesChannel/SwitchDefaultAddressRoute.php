<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    public function __construct(EntityRepositoryInterface $addressRepository, EntityRepositoryInterface $customerRepository)
    {
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
    }

    public function getDecorated(): AbstractSwitchDefaultAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Patch(
     *      path="/account/address/default-shipping/{addressId}",
     *      description="Sets the default shipping address",
     *      operationId="defaultShippingAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @OA\Patch(
     *      path="/account/address/default-billing/{addressId}",
     *      description="Sets the default billing address",
     *      operationId="defaultBillingAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/address/default-shipping/{addressId}", name="store-api.account.address.change.default.shipping", methods={"PATCH"}, defaults={"type" = "shipping"})
     * @Route(path="/store-api/v{version}/account/address/default-billing/{addressId}", name="store-api.account.address.change.default.billing", methods={"PATCH"}, defaults={"type" = "billing"})
     */
    public function swap(string $addressId, string $type, SalesChannelContext $context): NoContentResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $this->validateAddress($addressId, $context);

        switch ($type) {
            case 'billing':
                $data = [
                    'id' => $context->getCustomer()->getId(),
                    'defaultBillingAddressId' => $addressId,
                ];

                break;
            default:
                $data = [
                    'id' => $context->getCustomer()->getId(),
                    'defaultShippingAddressId' => $addressId,
                ];

                break;
        }

        $this->customerRepository->update([$data], $context->getContext());

        return new NoContentResponse();
    }
}
