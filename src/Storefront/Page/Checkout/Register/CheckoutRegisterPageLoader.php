<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        EntityRepositoryInterface $salutationRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->addressService = $addressService;
        $this->cartService = $cartService;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): CheckoutRegisterPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = CheckoutRegisterPage::createFrom($page);

        $page->setCountries(
            $this->addressService->getCountryList($context)
        );

        $page->setCart(
            $this->cartService->getCart($context->getToken(), $context)
        );

        $page->setSalutations($this->getSalutations($context));

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            $address = $this->addressService->getById((string) $addressId, $context);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            new CheckoutRegisterPageLoadedEvent($page, $context, $request),
            CheckoutRegisterPageLoadedEvent::NAME
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
