<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CannotDeleteDefaultAddressException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @OA\Delete(
     *      path="/account/address/{addressId}",
     *      description="Deletes a customer address",
     *      operationId="deleteCustomerAddress",
     *      tags={"Store API", "Account", "Address"},
     *      @OA\Response(
     *          response="204",
     *          description=""
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/address/{addressId}", name="store-api.account.address.delete", methods={"DELETE"})
     */
    public function delete(string $addressId, SalesChannelContext $context): NoContentResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $this->validateAddress($addressId, $context);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        if ($addressId === $customer->getDefaultBillingAddressId()
            || $addressId === $customer->getDefaultShippingAddressId()) {
            throw new CannotDeleteDefaultAddressException($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        return new NoContentResponse();
    }
}
