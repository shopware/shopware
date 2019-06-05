<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutFinishPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository,
        GenericPageLoader $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
        $this->genericLoader = $genericLoader;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    public function load(Request $request, SalesChannelContext $context): CheckoutFinishPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = CheckoutFinishPage::createFrom($page);

        $page->setOrder($this->getOrder($request, $context));

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageLoadedEvent($page, $context, $request),
            CheckoutFinishPageLoadedEvent::NAME
        );

        return $page;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    private function getOrder(Request $request, SalesChannelContext $context): OrderEntity
    {
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        $orderId = $request->get('orderId');
        if (!$orderId) {
            throw new MissingRequestParameterException('orderId', '/orderId');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        $criteria->addAssociationPath('lineItems.cover');

        try {
            $searchResult = $this->orderRepository->search($criteria, $context->getContext());
        } catch (InvalidUuidException $e) {
            throw new OrderNotFoundException($orderId);
        }

        if ($searchResult->count() !== 1) {
            throw new OrderNotFoundException($orderId);
        }

        return $searchResult->first();
    }
}
