<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class DeleteAddressRoute extends AbstractDeleteAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    public function __construct(EntityRepositoryInterface $addressRepository)
    {
        $this->addressRepository = $addressRepository;
    }

    public function getDecorated(): AbstractDeleteAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Delete(
     *      path="/account/address/{addressId}",
     *      summary="Delete an address of a customer",
     *      description="Delete an address of customer.

Only addresses which are not set as default addresses for shipping or billing can be deleted. You can check the current default addresses of your customer using the profile information endpoint and change them using the default address endpoint.

     **A customer must have at least one address (which can be used for shipping and billing).**

An automatic fallback is not applied.",
     *      operationId="deleteCustomerAddress",
     *      tags={"Store API", "Address"},
     *      @OA\Parameter(
     *        name="addressId",
     *        in="path",
     *        description="ID of the address to be deleted.",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="204",
     *          description="No Content response, when the address has been deleted"
     *     ),
     *      @OA\Response(
     *          response="400",
     *          description="Response containing a list of errors, most likely due to the address being in use"
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route(path="/store-api/account/address/{addressId}", name="store-api.account.address.delete", methods={"DELETE"})
     */
    public function delete(string $addressId, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $customer);

        if ($addressId === $customer->getDefaultBillingAddressId()
            || $addressId === $customer->getDefaultShippingAddressId()) {
            throw new CannotDeleteDefaultAddressException($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        return new NoContentResponse();
    }
}
