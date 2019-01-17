<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AccountPaymentMethodPageletLoader
{
    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(RepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function load(AccountPaymentMethodPageletRequest $request, CheckoutContext $context): AccountPaymentMethodPageletStruct
    {
        // todo@dr remove request, provide storefront context, provide calculated cart, use context rule system to validate
        $criteria = $this->createCriteria($request);
        $PaymentMethod = $this->paymentMethodRepository->search($criteria, $context->getContext());

        $page = new AccountPaymentMethodPageletStruct();
        $page->setPaymentMethod(new PaymentMethodCollection($PaymentMethod->getElements()));

        return $page;
    }

    private function createCriteria(AccountPaymentMethodPageletRequest $request): Criteria
    {
        $limit = $request->getLimit();
        $page = $request->getPage();

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new EqualsFilter('payment_method.active', 1));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $criteria;
    }
}
