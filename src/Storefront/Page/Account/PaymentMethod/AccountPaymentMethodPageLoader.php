<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelRepository
     */
    private $paymentMethodRepository;

    public function __construct(
        SalesChannelRepository $paymentMethodRepository,
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @throws CustomerNotLoggedInException
     */
    public function load(Request $request, SalesChannelContext $context): AccountPaymentMethodPage
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $context);

        $page = AccountPaymentMethodPage::createFrom($page);

        $criteria = $this->createCriteria($request);

        $page->setPaymentMethods(
            $this->paymentMethodRepository->search($criteria, $context)
        );

        $this->eventDispatcher->dispatch(
            new AccountPaymentMethodPageLoadedEvent($page, $context, $request),
            AccountPaymentMethodPageLoadedEvent::NAME
        );

        return $page;
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('p', 1);

        return (new Criteria())
            ->setOffset(($page - 1) * $limit)
            ->setLimit($limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addSorting(new FieldSorting('position'));
    }
}
