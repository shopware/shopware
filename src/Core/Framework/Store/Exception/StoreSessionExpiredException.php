<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('merchant-services')]
class StoreSessionExpiredException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store session has expired');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_SESSION_EXPIRED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
