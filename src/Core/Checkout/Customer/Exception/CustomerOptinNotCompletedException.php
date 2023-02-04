<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerOptinNotCompletedException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.6.0 the $message parameter will be removed without replacement
     */
    public function __construct(
        string $id,
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? 'The customer with the id "{{ customerId }}" has not completed the opt-in.',
            ['customerId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_OPTIN_NOT_COMPLETED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getSnippetKey(): string
    {
        return 'account.doubleOptinAccountAlert';
    }
}
