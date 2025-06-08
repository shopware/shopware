<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class AddressNotFoundException extends CustomerException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_NOT_FOUND,
            'Customer address with id "{{ addressId }}" not found.',
            ['addressId' => $id]
        );
    }
}
