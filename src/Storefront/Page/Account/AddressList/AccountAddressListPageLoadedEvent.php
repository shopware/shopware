<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\AddressList;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AccountAddressListPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-address-list.page.loaded';

    /**
     * @var AccountAddressListPage
     */
    protected $page;

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(AccountAddressListPage $page, SalesChannelContext $context, InternalRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPage(): AccountAddressListPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
