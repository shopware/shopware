<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Exception\CustomerGroupRegistrationConfigurationNotFound;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class CustomerGroupRegistrationSettingsRoute extends AbstractCustomerGroupRegistrationSettingsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $customerGroupRepository)
    {
    }

    public function getDecorated(): AbstractCustomerGroupRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/customer-group-registration/config/{customerGroupId}', name: 'store-api.customer-group-registration.config', methods: ['GET'])]
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
