<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AccountService $accountService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->accountService = $accountService;
    }

    public function load(Request $request, SalesChannelContext $context): AccountProfilePage
    {
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $context);

        $page = AccountProfilePage::createFrom($page);

        $page->setSalutations($this->accountService->getSalutationList($context));

        $this->eventDispatcher->dispatch(
            AccountProfilePageLoadedEvent::NAME,
            new AccountProfilePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
