<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutRegisterPageLoader
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
     * @var CartService
     */
    private $cartService;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        SalesChannelRepositoryInterface $salutationRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->addressService = $addressService;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->salutationRepository = $salutationRepository;
    }

    /**
     * @throws AddressNotFoundException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutRegisterPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutRegisterPage::createFrom($page);

        $page->setCountries($this->addressService->getCountryList($salesChannelContext));

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            $address = $this->addressService->getById((string) $addressId, $salesChannelContext);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            new CheckoutRegisterPageLoadedEvent($page, $salesChannelContext, $request)
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
