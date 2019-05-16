<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOverviewPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
    }

    public function load(Request $request, SalesChannelContext $context): AccountOverviewPage
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $context);

        $page = AccountOverviewPage::createFrom($page);

        $this->eventDispatcher->dispatch(
            AccountOverviewPageLoadedEvent::NAME,
            new AccountOverviewPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
