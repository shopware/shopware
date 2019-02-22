<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;

interface PageLoaderInterface
{
    public function load(InternalRequest $request, CheckoutContext $context);
}
