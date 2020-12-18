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
     *      summary="Deletes a customer address",
     *      operationId="deleteCustomerAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Parameter(
     *        name="addressId",
     *        in="path",
     *        description="Address ID",
     *        @OA\Schema(type="string"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="204",
     *          description=""
     *     )
     * )
     * @LoginRequired()
     * @Route(path="/store-api/v{version}/account/address/{addressId}", name="store-api.account.address.delete", methods={"DELETE"})
     */
    public function delete(string $addressId, SalesChannelContext $context, ?CustomerEntity $customer = null): NoContentResponse
    {
        /* @deprecated tag:v6.4.0 - Parameter $customer will be mandatory when using with @LoginRequired() */
        if (!$customer) {
            /** @var CustomerEntity $customer */
            $customer = $context->getCustomer();
        }

        $this->validateAddress($addressId, $context);

        if ($addressId === $customer->getDefaultBillingAddressId()
            || $addressId === $customer->getDefaultShippingAddressId()) {
            throw new CannotDeleteDefaultAddressException($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        return new NoContentResponse();
    }
}
