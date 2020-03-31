<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractSalutationRoute
     */
    private $salutationRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractSalutationRoute $salutationRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->salutationRoute = $salutationRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountProfilePage
    {
        if ($salesChannelContext->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountProfilePage::createFrom($page);

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountProfilePageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext)->getSalutations();

        $salutations->sort(static function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

        return $salutations;
    }
}
