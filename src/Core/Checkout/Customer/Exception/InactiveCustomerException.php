<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package customer-order
 *
 * @deprecated tag:v6.6.0 Will be removed without replacement, not in use any more. Use `BadCredentialsException` or `CustomerOptinNotCompletedException` instead
 */
class InactiveCustomerException extends CustomerOptinNotCompletedException
{
    public function __construct(string $id)
    {
        parent::__construct($id, 'The customer with the id "{{ customerId }}" is inactive.');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        return 'CHECKOUT__CUSTOMER_IS_INACTIVE';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        return Response::HTTP_UNAUTHORIZED;
    }

    public function getSnippetKey(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        return 'account.inactiveAccountAlert';
    }
}
