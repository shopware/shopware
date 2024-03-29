<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerNotFoundException extends CustomerException
{
    public function __construct(string $email)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_NOT_FOUND,
            'No matching customer for the email "{{ email }}" was found.',
            ['email' => $email]
        );
    }
}
