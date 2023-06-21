<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::customerGroupRegistrationConfigurationNotFound instead
 */
#[Package('customer-order')]
class CustomerGroupRegistrationConfigurationNotFound extends CustomerException
{
    public function __construct(string $customerGroupId)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND,
            'Customer group registration for id {{ customerGroupId }} not found.',
            ['customerGroupId' => $customerGroupId]
        );
    }
}
