<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentCurrency;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ContentCurrencyPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'content-currency.pagelet.loaded.event';

    /**
     * @var ContentCurrencyPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ContentCurrencyPageletRequest
     */
    protected $request;

    public function __construct(
        ContentCurrencyPageletStruct $pagelet,
        CheckoutContext $context,
        ContentCurrencyPageletRequest $request
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

    public function getPagelet(): ContentCurrencyPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ContentCurrencyPageletRequest
    {
        return $this->request;
    }
}
