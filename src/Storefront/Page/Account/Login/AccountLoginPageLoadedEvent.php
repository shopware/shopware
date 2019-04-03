<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AccountLoginPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-login.page.loaded';

    /**
     * @var AccountLoginPage
     */
    protected $page;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(AccountLoginPage $page, SalesChannelContext $context, InternalRequest $request)
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

    public function getPage(): AccountLoginPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
