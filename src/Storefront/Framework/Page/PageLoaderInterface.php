<?php

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Page\Home\HomePage;

interface PageLoaderInterface
{
    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     * @return GenericPage
     */
    public function load(InternalRequest $request, CheckoutContext $context);
}