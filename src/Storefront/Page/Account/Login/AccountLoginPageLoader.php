<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepositoryInterface $salutationRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->addressService = $addressService;
        $this->eventDispatcher = $eventDispatcher;
        $this->salutationRepository = $salutationRepository;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountLoginPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountLoginPage::createFrom($page);

        $page->setCountries($this->addressService->getCountryList($salesChannelContext));
        $page->setSalutations($this->getSalutations($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountLoginPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $criteria = (new Criteria([]))->addSorting(new FieldSorting('salutationKey', 'DESC'));

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search($criteria, $salesChannelContext)->getEntities();

        return $salutations;
    }
}
