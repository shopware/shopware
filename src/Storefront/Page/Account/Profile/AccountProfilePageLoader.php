<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageLoader
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
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $salutationRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): AccountProfilePage
    {
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $context);

        $page = AccountProfilePage::createFrom($page);

        $page->setSalutations($this->getSalutations($context));

        $this->eventDispatcher->dispatch(
            AccountProfilePageLoadedEvent::NAME,
            new AccountProfilePageLoadedEvent($page, $context, $request)
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
