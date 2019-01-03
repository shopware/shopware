<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Currency;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class CurrencyPageletRequestEvent extends NestedEvent
{
    public const NAME = 'content.currency.pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var CurrencyPageletRequest
     */
    private $currencyPageletRequest;

    public function __construct(
        Request $request, CheckoutContext $context, CurrencyPageletRequest $currencyPageletRequest
    ) {
        $this->request = $request;
        $this->context = $context;
        $this->currencyPageletRequest = $currencyPageletRequest;
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

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getIndexPageRequest(): CurrencyPageletRequest
    {
        return $this->currencyPageletRequest;
    }
}
