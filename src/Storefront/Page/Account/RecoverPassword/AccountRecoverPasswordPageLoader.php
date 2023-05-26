<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\RecoverPassword;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('customer-order')]
class AccountRecoverPasswordPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CustomerRecoveryIsExpiredRoute $recoveryIsExpiredRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws ConstraintViolationException
     */
    public function load(Request $request, SalesChannelContext $context, string $hash): AccountRecoverPasswordPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AccountRecoverPasswordPage::createFrom($page);
        $page->setHash($hash);

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $customerRecoveryResponse = $this->recoveryIsExpiredRoute
            ->load(new RequestDataBag(['hash' => $hash]), $context);

        $page->setHashExpired($customerRecoveryResponse->isExpired());

        $this->eventDispatcher->dispatch(
            new AccountRecoverPasswordPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
