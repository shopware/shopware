<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ContentLanguagePageletLoadedEvent extends NestedEvent
{
    public const NAME = 'content-language.pagelet.loaded.event';

    /**
     * @var ContentLanguagePageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ContentLanguagePageletRequest
     */
    protected $request;

    public function __construct(
        ContentLanguagePageletStruct $pagelet,
        CheckoutContext $context,
        ContentLanguagePageletRequest $request
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

    public function getPagelet(): ContentLanguagePageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ContentLanguagePageletRequest
    {
        return $this->request;
    }
}
