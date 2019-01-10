<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AccountLoginPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-login.pagelet.loaded.event';

    /**
     * @var AccountLoginPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountLoginPageletRequest
     */
    protected $request;

    public function __construct(
        AccountLoginPageletStruct $pagelet,
        CheckoutContext $context,
        AccountLoginPageletRequest $request
    ) {
        $this->pagelet = $pagelet;
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

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPagelet(): AccountLoginPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): AccountLoginPageletRequest
    {
        return $this->request;
    }
}
