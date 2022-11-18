<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CustomerRoute extends AbstractCustomerRoute
{
    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
    }

    public function getDecorated(): AbstractCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("customer")
     * @Route("/store-api/account/customer", name="store-api.account.customer", methods={"GET", "POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): CustomerResponse
    {
        $criteria->setIds([$customer->getId()]);

        $customerEntity = $this->customerRepository->search($criteria, $context->getContext())->first();

        return new CustomerResponse($customerEntity);
    }
}
