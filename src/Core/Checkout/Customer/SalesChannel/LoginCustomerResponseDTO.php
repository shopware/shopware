<?php declare(strict_types=1);

// This file is auto-generated. Do not edit manually.

namespace Shopware\Core\Checkout\Customer\SalesChannel;

/**
 * Returns the context token. Use that as your `sw-context-token` header for subsequent requests. Redirect if getRedirectUrl is set.
 */
class LoginCustomerResponseDTO
{
    public function __construct(
        /** Define the URL which browser will be redirected to */
        public ?string $redirectUrl = null,
    ) {
    }
}
