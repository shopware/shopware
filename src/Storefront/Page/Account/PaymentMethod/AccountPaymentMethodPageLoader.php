<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageLoader
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepositoryInterface $paymentMethodRepository,
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountPaymentMethodPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountPaymentMethodPage::createFrom($page);

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountPaymentMethodPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = (new Criteria())
            ->addAssociation('media')
            ->addFilter(new EqualsFilter('active', true));

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $salesChannelContext)->getEntities();

        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $paymentMethods->filterByActiveRules($salesChannelContext);
    }
}
