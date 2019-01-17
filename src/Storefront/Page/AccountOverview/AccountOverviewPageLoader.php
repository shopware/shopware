<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountOverviewPageLoader
{
    /**
     * @var AccountProfilePageletLoader
     */
    private $accountProfilePageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountProfilePageletLoader $accountProfilePageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->accountProfilePageletLoader = $accountProfilePageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param AccountOverviewPageRequest $request
     * @param CheckoutContext            $context
     *
     * @return AccountOverviewPageStruct
     */
    public function load(AccountOverviewPageRequest $request, CheckoutContext $context): AccountOverviewPageStruct
    {
        $page = new AccountOverviewPageStruct();
        $page->setAccountProfile(
            $this->accountProfilePageletLoader->load($request->getAccountProfileRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            AccountOverviewPageLoadedEvent::NAME,
            new AccountOverviewPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
