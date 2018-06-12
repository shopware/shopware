<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Symfony\Component\HttpFoundation\Request;

class PaymentMethodLoader
{
    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(RepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function load(Request $request, Context $context)
    {
        // todo@dr remove request, provide storefront context, provide calculated cart, use context rule system to validate
        $criteria = $this->createCriteria($request);
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context);

        return new PaymentMethodCollection($paymentMethods->getElements());
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->query->getInt('limit', 20);
        $page = $request->query->getInt('page', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->addFilter(new TermQuery('payment_method.active', 1));
        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);

        return $criteria;
    }
}
