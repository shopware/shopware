<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class PasswordPoliciesUpdatedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Password policies updated.'
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PASSWORD_POLICIES_UPDATED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
