<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Exception\CustomerGroupRegistrationConfigurationNotFound;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CustomerGroupRegistrationSettingsRoute extends AbstractCustomerGroupRegistrationSettingsRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;

    public function __construct(EntityRepositoryInterface $customerGroupRepository)
    {
        $this->customerGroupRepository = $customerGroupRepository;
    }

    public function getDecorated(): AbstractCustomerGroupRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Get(
     *      path="/customer-group-registration/config/{customerGroupId}",
     *      description="Change email",
     *      operationId="getCustomerGroupRegistrationInfo",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *        name="customerGroupId",
     *        in="url",
     *        description="Customer Group ID",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(ref="#/definitions/CustomerGroup")
     *     )
     * )
     * @Route(path="/store-api/v{version}/customer-group-registration/config/{customerGroupId}", name="store-api.customer-group-registration.config", methods={"GET"})
     */
    public function load(string $customerGroupId, SalesChannelContext $context): CustomerGroupRegistrationSettingsRouteResponse
    {
        $criteria = new Criteria([$customerGroupId]);
        $criteria->addFilter(new EqualsFilter('registrationActive', 1));
        $criteria->addFilter(new EqualsFilter('registrationSalesChannels.id', $context->getSalesChannel()->getId()));

        $result = $this->customerGroupRepository->search($criteria, $context->getContext());

        if ($result->getTotal() === 0) {
            throw new CustomerGroupRegistrationConfigurationNotFound($customerGroupId);
        }

        return new CustomerGroupRegistrationSettingsRouteResponse($result->first());
    }
}
