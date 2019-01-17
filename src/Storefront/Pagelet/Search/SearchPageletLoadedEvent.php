<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class SearchPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'search.pagelet.loaded.event';

    /**
     * @var SearchPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var SearchPageletRequest
     */
    protected $request;

    public function __construct(
        SearchPageletStruct $pagelet,
        CheckoutContext $context,
        SearchPageletRequest $request
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

    public function getPagelet(): SearchPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): SearchPageletRequest
    {
        return $this->request;
    }
}
