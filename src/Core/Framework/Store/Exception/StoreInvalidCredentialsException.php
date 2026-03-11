<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, use StoreException::invalidCredentials() instead
 */
class StoreInvalidCredentialsException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::STORE_INVALID_CREDENTIALS,
            'Invalid credentials'
        );
    }
}
