<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

interface ListingPageletRequestEventInterface
{
    public function getName(): string;

    public function getContext(): Context;

    public function getCheckoutContext(): CheckoutContext;

    public function getRequest(): Request;

    public function getListingPageletRequest(): ListingPageletRequestInterface;
}
