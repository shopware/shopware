<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class StoreTokenMissingException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store token is missing');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
