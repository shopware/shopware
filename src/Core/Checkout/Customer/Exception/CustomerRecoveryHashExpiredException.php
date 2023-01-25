<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerRecoveryHashExpiredException extends ShopwareHttpException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            'The hash "{{ hash }}" is expired.',
            ['hash' => $hash]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_RECOVERY_HASH_EXPIRED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_GONE;
    }
}
