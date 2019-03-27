<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AddressNotFoundException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'Customer address with id "{{ customerId }}" not found.',
            ['customerId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_ADDRESS_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
