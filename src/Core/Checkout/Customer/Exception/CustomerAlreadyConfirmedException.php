<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerAlreadyConfirmedException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'The customer with the id "{{ customerId }}" is already confirmed.',
            ['customerId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_IS_ALREADY_CONFIRMED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
