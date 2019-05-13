<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $salutationRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->addressService = $addressService;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): AccountLoginPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountLoginPage::createFrom($page);

        $page->setCountries($this->addressService->getCountryList($context));
        $page->setSalutations($this->getSalutations($context));

        $this->eventDispatcher->dispatch(
            new AccountLoginPageLoadedEvent($page, $context, $request),
            AccountLoginPageLoadedEvent::NAME
        );

        return $page;
    }

    private function getSalutations(SalesChannelContext $context): SalutationCollection
    {
        $criteria = (new Criteria([]))
            ->addSorting(new FieldSorting('salutationKey', 'DESC'));

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $salutations;
    }
}
