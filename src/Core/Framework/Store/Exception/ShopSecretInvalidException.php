<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class ShopSecretInvalidException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            $this->getStatusCode(),
            $this->getErrorCode(),
            'Store shop secret is invalid'
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_SHOP_SECRET_INVALID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
