<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AccountPaymentMethodPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-payment-method.page.loaded';

    /**
     * @var AccountPaymentMethodPage
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

    public function __construct(AccountPaymentMethodPage $page, SalesChannelContext $context, InternalRequest $request)
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

    public function getPage(): AccountPaymentMethodPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
