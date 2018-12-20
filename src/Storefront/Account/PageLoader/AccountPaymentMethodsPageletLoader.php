<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Checkout\Page\PaymentMethodPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodsPageletLoader implements PageLoader
{
    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(RepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function load(PageRequest $request, CheckoutContext $context): PaymentMethodPageletStruct
    {
        // todo@dr remove request, provide storefront context, provide calculated cart, use context rule system to validate
        $criteria = $this->createCriteria($request->getHttpRequest());
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context->getContext());

        return new PaymentMethodPageletStruct(new PaymentMethodCollection($paymentMethods->getElements()));
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new EqualsFilter('payment_method.active', 1));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $criteria;
    }
}
