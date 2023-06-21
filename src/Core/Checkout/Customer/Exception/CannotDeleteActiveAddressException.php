<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::cannotDeleteActiveAddress instead
 */
#[Package('customer-order')]
class CannotDeleteActiveAddressException extends CustomerException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_IS_ACTIVE,
            'Customer address with id "{{ addressId }}" is an active address and cannot be deleted.',
            ['addressId' => $id]
        );
    }
}
