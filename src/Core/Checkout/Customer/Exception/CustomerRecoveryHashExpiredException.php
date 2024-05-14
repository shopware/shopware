<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerRecoveryHashExpiredException extends CustomerException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_GONE,
            self::CUSTOMER_RECOVERY_HASH_EXPIRED,
            'The hash "{{ hash }}" is expired.',
            ['hash' => $hash]
        );
    }
}
