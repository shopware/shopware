<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

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
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountPaymentMethodPage
    {
        return $this->page;
    }
}
