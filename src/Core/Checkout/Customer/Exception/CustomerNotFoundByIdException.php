<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerNotFoundByIdException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'No matching customer for the id "{{ id }}" was found.',
            ['id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
