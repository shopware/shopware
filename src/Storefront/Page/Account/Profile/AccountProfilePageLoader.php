<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Event\RouteRequest\SalutationRouteRequestEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('customer-order')]
class AccountProfilePageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractSalutationRoute $salutationRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountProfilePage
    {
        if ($salesChannelContext->getCustomer() === null) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountProfilePage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setSalutations($this->getSalutations($salesChannelContext, $request));

        $this->eventDispatcher->dispatch(
            new AccountProfilePageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getSalutations(SalesChannelContext $context, Request $request): SalutationCollection
    {
        $event = new SalutationRouteRequestEvent($request, new Request(), $context, new Criteria());
        $this->eventDispatcher->dispatch($event);

        $salutations = $this->salutationRoute
            ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
            ->getSalutations();

        $salutations->sort(static fn (SalutationEntity $a, SalutationEntity $b) => $b->getSalutationKey() <=> $a->getSalutationKey());

        return $salutations;
    }
}
