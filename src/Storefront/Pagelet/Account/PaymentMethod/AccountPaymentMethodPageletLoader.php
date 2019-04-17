<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Account\PaymentMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageletLoader implements PageLoaderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $paymentMethodRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = $this->createCriteria($request);

        $pagelet = $this->paymentMethodRepository->search($criteria, $context->getContext());

        $this->eventDispatcher->dispatch(
            AccountPaymentMethodPageletLoadedEvent::NAME,
            new AccountPaymentMethodPageletLoadedEvent($pagelet, $context, $request)
        );

        return $pagelet;
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('p', 1);

        $criteria = new Criteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $criteria;
    }
}
