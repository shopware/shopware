<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerNotFoundException extends ShopwareHttpException
{
    public function __construct(string $email)
    {
        parent::__construct(
            'No matching customer for the email "{{ email }}" was found.',
            ['email' => $email]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
