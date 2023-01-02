<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerGroupRegistrationConfigurationNotFound extends ShopwareHttpException
{
    public function __construct(string $customerGroupId)
    {
        parent::__construct(
            'Customer group registration for id {{ customerGroupId }} not found.',
            ['customerGroupId' => $customerGroupId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_GROUP_REGISTRATION_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
