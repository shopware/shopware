<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;

class ContentHomePageletLoader
{
    public function __construct()
    {
    }

    public function load(InternalRequest $request, CheckoutContext $context): ContentHomePageletStruct
    {
        return new ContentHomePageletStruct();
    }
}
