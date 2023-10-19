<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidCustomerIdException extends ShopwareHttpException
{
    public function __construct(string $customerId)
    {
        parent::__construct(
            'The provided customerId "{{ customerId }}" is invalid.',
            ['customerId' => $customerId]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CUSTOMER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
