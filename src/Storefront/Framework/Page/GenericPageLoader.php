<?php

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;

class GenericPageLoader
{
    /**
     * @var HeaderPageletLoader
     */
    private $headerLoader;

    public function __construct(HeaderPageletLoader $headerLoader)
    {
        $this->headerLoader = $headerLoader;
    }

    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     *
     * @return GenericPage
     */
    public function load(InternalRequest $request, CheckoutContext $context)
    {
        return new GenericPage(
            $this->headerLoader->load($request, $context)
        );
    }
}