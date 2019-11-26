<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InactiveCustomerException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'The customer with the id "{{ customerId }}" is inactive.',
            ['customerId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_IS_INACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getSnippetKey(): string
    {
        return 'account.inactiveAccountAlert';
    }
}
