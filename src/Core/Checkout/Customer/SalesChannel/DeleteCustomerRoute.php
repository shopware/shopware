<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class DeleteCustomerRoute extends AbstractDeleteCustomerRoute
{
    private EntityRepository $customerRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
    }

    public function getDecorated(): AbstractDeleteCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @Route("/store-api/account/customer", name="store-api.account.customer.delete", methods={"DELETE"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function delete(SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->customerRepository->delete([['id' => $customer->getId()]], $context->getContext());

        return new NoContentResponse();
    }
}
