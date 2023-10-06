<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - Will be removed without replacement, not in use any more. Use `BadCredentialsException` or `CustomerOptinNotCompletedException` instead
 */
#[Package('checkout')]
class InactiveCustomerException extends CustomerOptinNotCompletedException
{
    public function __construct(string $id)
    {
        parent::__construct(
            $id,
            'The customer with the id "{{ customerId }}" is inactive.',
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_IS_INACTIVE,
        );
    }

    public function getSnippetKey(): string
    {
        return 'account.inactiveAccountAlert';
    }
}
