<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\CustomerGroupRegistration;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('customer-order')]
class CustomerGroupRegistrationPageLoader extends AbstractCustomerGroupRegistrationPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountLoginPageLoader $accountLoginPageLoader,
        private readonly AbstractCustomerGroupRegistrationSettingsRoute $customerGroupRegistrationRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): CustomerGroupRegistrationPage
    {
        $page = CustomerGroupRegistrationPage::createFrom($this->accountLoginPageLoader->load($request, $salesChannelContext));
        $customerGroupId = $request->attributes->get('customerGroupId');

        $page->setGroup(
            $this->customerGroupRegistrationRoute->load($customerGroupId, $salesChannelContext)->getRegistration()
        );

        if ($page->getMetaInformation()) {
            $metaDescription = $page->getGroup()->getTranslation('registrationSeoMetaDescription');
            if ($metaDescription) {
                $page->getMetaInformation()->setMetaDescription($metaDescription);
            }

            $title = $page->getGroup()->getTranslation('registrationTitle');
            if ($title) {
                $page->getMetaInformation()->setMetaTitle($title);
            }
        }

        $this->eventDispatcher->dispatch(new CustomerGroupRegistrationPageLoadedEvent($page, $salesChannelContext, $request));

        return $page;
    }

    public function getDecorated(): AbstractCustomerGroupRegistrationPageLoader
    {
        throw new DecorationPatternException(self::class);
    }
}
