<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class InvalidImitateCustomerTokenException extends CustomerException
{
    public function __construct(string $token)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::IMITATE_CUSTOMER_INVALID_TOKEN,
            'The token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }
}
