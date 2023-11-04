<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class DeleteCustomerRoute extends AbstractDeleteCustomerRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
    }

    public function getDecorated(): AbstractDeleteCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/customer', name: 'store-api.account.customer.delete', methods: ['DELETE'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
    public function delete(SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->customerRepository->delete([['id' => $customer->getId()]], $context->getContext());

        return new NoContentResponse();
    }
}
