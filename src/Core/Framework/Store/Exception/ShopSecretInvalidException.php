<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('merchant-services')]
class ShopSecretInvalidException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store shop secret is invalid');
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
