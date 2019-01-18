<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;

interface PageLoaderInterface
{
    /**
     * @param InternalRequest $request
     * @param CheckoutContext $context
     */
    public function load(InternalRequest $request, CheckoutContext $context);
}
