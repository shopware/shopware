<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\CustomerGroupRegistration;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerGroupRegistrationPageLoader extends AbstractCustomerGroupRegistrationPageLoader
{
    /**
     * @var AbstractCustomerGroupRegistrationSettingsRoute
     */
    private $customerGroupRegistrationRoute;

    /**
     * @var AccountLoginPageLoader
     */
    private $accountLoginPageLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AccountLoginPageLoader $accountLoginPageLoader,
        AbstractCustomerGroupRegistrationSettingsRoute $customerGroupRegistrationRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->customerGroupRegistrationRoute = $customerGroupRegistrationRoute;
        $this->accountLoginPageLoader = $accountLoginPageLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): CustomerGroupRegistrationPage
    {
        $page = CustomerGroupRegistrationPage::createFrom($this->accountLoginPageLoader->load($request, $salesChannelContext));
        $customerGroupId = $request->attributes->get('customerGroupId');

        $page->setGroup(
            $this->customerGroupRegistrationRoute->load($customerGroupId, $salesChannelContext)->getRegistration()
        );

        if ($metaDescription = $page->getGroup()->getTranslation('registrationSeoMetaDescription')) {
            $page->getMetaInformation()->setMetaDescription($metaDescription);
        }

        if ($title = $page->getGroup()->getTranslation('registrationTitle')) {
            $page->getMetaInformation()->setMetaTitle($title);
        }

        $this->eventDispatcher->dispatch(new CustomerGroupRegistrationPageLoadedEvent($page, $salesChannelContext, $request));

        return $page;
    }

    public function getDecorated(): AbstractCustomerGroupRegistrationPageLoader
    {
        throw new DecorationPatternException(self::class);
    }
}
