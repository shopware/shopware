<?php

namespace Shopware\Storefront\Pagelet\Language;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class LanguagePageletLoadedEvent extends NestedEvent
{
    public const NAME = 'language.pagelet.loaded';

    /**
     * @var LanguagePageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var LanguagePageletRequest
     */
    protected $request;

    public function __construct(
        LanguagePageletStruct $pagelet,
        CheckoutContext $context,
        LanguagePageletRequest $request
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

    public function getPagelet(): LanguagePageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): LanguagePageletRequest
    {
        return $this->request;
    }
}