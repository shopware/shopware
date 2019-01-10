<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AccountProfilePageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-profile.pagelet.loaded.event';

    /**
     * @var AccountProfilePageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountProfilePageletRequest
     */
    protected $request;

    public function __construct(
        AccountProfilePageletStruct $pagelet,
        CheckoutContext $context,
        AccountProfilePageletRequest $request
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

    public function getPagelet(): AccountProfilePageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): AccountProfilePageletRequest
    {
        return $this->request;
    }
}
