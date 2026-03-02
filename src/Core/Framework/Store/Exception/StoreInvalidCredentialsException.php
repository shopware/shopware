<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StoreInvalidCredentialsException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->getErrorCode(),
            'Invalid credentials'
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_INVALID_CREDENTIALS';
    }
}
