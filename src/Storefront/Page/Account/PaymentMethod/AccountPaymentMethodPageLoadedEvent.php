<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - this page is removed as customer default payment method will be removed
 */
#[Package('storefront')]
class AccountPaymentMethodPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountPaymentMethodPage
     */
    protected $page;

    public function __construct(
        AccountPaymentMethodPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountPaymentMethodPage
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');

        return $this->page;
    }
}
