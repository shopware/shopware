<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class CustomerRoute extends AbstractCustomerRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
    }

    public function getDecorated(): AbstractCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/customer', name: 'store-api.account.customer', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true, '_entity' => 'customer'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): CustomerResponse
    {
        $criteria->setIds([$customer->getId()]);

        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        return new CustomerResponse($customerEntity);
    }
}
